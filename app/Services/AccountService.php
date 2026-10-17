<?php

namespace App\Services;

use App\Enums\AccountTransferStatus;
use App\Enums\TransactionCategory;
use App\Enums\TransactionType;
use App\Exceptions\AccountException;
use App\Models\Account;
use App\Models\AccountTransfer;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AccountService
{
    /**
     * Crée un compte financier et, si nécessaire, une transaction d'ouverture.
     *
     * @param  array<string, mixed>  $data
     */
    public function createAccount(array $data): Account
    {
        return DB::transaction(function () use ($data): Account {
            if (empty($data['code'])) {
                $data['code'] = $this->generateAccountCode((int) $data['organisation_id']);
            }

            $data['current_balance'] = $data['initial_balance'] ?? 0;

            $isFirst = ! Account::query()
                ->where('organisation_id', $data['organisation_id'])
                ->exists();

            if ($isFirst) {
                $data['is_default'] = true;
            }

            $account = Account::create($data);

            if (! empty($data['is_default']) && ! $isFirst) {
                $account->setAsDefault();
            }

            if ((float) $account->initial_balance > 0) {
                // Datée du solde initial (reprise d'un exercice passé) et référencée pour ne pas être recomptée
                $account->transactions()->make([
                    'user_id' => auth()->id(),
                    'type' => TransactionType::Credit,
                    'category' => TransactionCategory::Opening,
                    'amount' => $account->initial_balance,
                    'reference_number' => Account::OPENING_REFERENCE,
                    'description' => "Solde d'ouverture du compte {$account->name}",
                    'transaction_date' => ($account->initial_balance_date ?? now())->toDateString(),
                ])->forceFill([
                    'balance_before' => 0,
                    'balance_after' => $account->initial_balance,
                ])->save();
            }

            return $account->fresh();
        });
    }

    /**
     * Effectue un transfert entre deux comptes de la même organisation, daté du jour par défaut.
     */
    public function executeTransfer(
        int $organisationId,
        int $fromAccountId,
        int $toAccountId,
        float $amount,
        float $fees = 0,
        ?string $description = null,
        ?string $notes = null,
        \DateTimeInterface|string|null $date = null,
    ): AccountTransfer {
        if ($amount <= 0) {
            throw AccountException::invalidAmount();
        }

        if ($fromAccountId === $toAccountId) {
            throw AccountException::sameAccountTransfer();
        }

        $fromAccount = Account::findOrFail($fromAccountId);
        $toAccount = Account::findOrFail($toAccountId);

        if ($fromAccount->organisation_id !== $organisationId || $toAccount->organisation_id !== $organisationId) {
            throw AccountException::organisationMismatch();
        }

        if (! $fromAccount->is_active) {
            throw AccountException::accountNotActive($fromAccount->name);
        }

        if (! $toAccount->is_active) {
            throw AccountException::accountNotActive($toAccount->name);
        }

        $total = $amount + $fees;

        if ((float) $fromAccount->current_balance < $total) {
            throw AccountException::insufficientBalance($fromAccount->name, $total, (float) $fromAccount->current_balance);
        }

        return DB::transaction(function () use ($organisationId, $fromAccount, $toAccount, $amount, $fees, $description, $notes, $date): AccountTransfer {
            $transfer = AccountTransfer::create([
                'organisation_id' => $organisationId,
                'from_account_id' => $fromAccount->id,
                'to_account_id' => $toAccount->id,
                'user_id' => auth()->id(),
                'amount' => $amount,
                'fees' => $fees,
                'transfer_date' => $date ? Carbon::parse($date)->toDateString() : now()->toDateString(),
                'status' => AccountTransferStatus::Pending,
                'description' => $description,
                'notes' => $notes,
            ]);

            $transfer->complete();

            return $transfer->fresh(['fromAccount', 'toAccount']);
        });
    }

    /**
     * Réapprovisionne un compte (caisse le plus souvent) depuis un autre compte de l'organisation :
     * un transfert interne complété, daté du jour indiqué.
     */
    public function replenish(
        Account $account,
        int $fromAccountId,
        float $amount,
        float $fees = 0,
        \DateTimeInterface|string|null $date = null,
        ?string $notes = null,
    ): AccountTransfer {
        return $this->executeTransfer(
            organisationId: (int) $account->organisation_id,
            fromAccountId: $fromAccountId,
            toAccountId: (int) $account->id,
            amount: $amount,
            fees: $fees,
            description: "Réapprovisionnement de {$account->name}",
            notes: $notes,
            date: $date,
        );
    }

    /**
     * Apport de fonds extérieur (exploitant, associés) sur un compte : crédit en catégorie
     * « contribution », imputé au compte de l'exploitant.
     */
    public function recordContribution(
        Account $account,
        float $amount,
        ?string $description = null,
        \DateTimeInterface|string|null $date = null,
        ?string $reference = null,
    ): Transaction {
        $this->assertCanBeFunded($account, $amount);

        return $account->credit(
            amount: $amount,
            description: $description ?: "Apport de fonds sur {$account->name}",
            category: TransactionCategory::Contribution,
            referenceNumber: $reference,
            date: $date,
        );
    }

    /**
     * Prélèvement de l'exploitant sur un compte : débit en catégorie « withdrawal », refusé si le
     * solde ne le couvre pas.
     */
    public function recordWithdrawal(
        Account $account,
        float $amount,
        ?string $description = null,
        \DateTimeInterface|string|null $date = null,
        ?string $reference = null,
    ): Transaction {
        $this->assertCanBeFunded($account, $amount);

        if ((float) $account->current_balance < $amount) {
            throw AccountException::insufficientBalance($account->name, $amount, (float) $account->current_balance);
        }

        return $account->debit(
            amount: $amount,
            description: $description ?: "Prélèvement de l'exploitant sur {$account->name}",
            category: TransactionCategory::Withdrawal,
            referenceNumber: $reference,
            date: $date,
        );
    }

    private function assertCanBeFunded(Account $account, float $amount): void
    {
        if ($amount <= 0) {
            throw AccountException::invalidAmount();
        }

        if (! $account->is_active) {
            throw AccountException::accountNotActive($account->name);
        }
    }

    /**
     * Marque comme rapproché un lot de transactions d'un même compte, puis
     * renvoie une synthèse indiquant d'éventuels écarts avec le solde du compte.
     *
     * @param  array<int>  $transactionIds
     * @return array{
     *     reconciled_count: int,
     *     total_credits: float,
     *     total_debits: float,
     *     net: float,
     *     balance_discrepancy: float,
     *     account_balance: float,
     * }
     */
    public function reconcileTransactions(Account $account, array $transactionIds): array
    {
        return DB::transaction(function () use ($account, $transactionIds): array {
            $transactions = $account->transactions()
                ->whereIn('id', $transactionIds)
                ->get();

            $transactions->each(fn (Transaction $transaction) => $transaction->reconcile());

            $totalCredits = (float) $transactions->where('type', TransactionType::Credit)->sum('amount');
            $totalDebits = (float) $transactions->where('type', TransactionType::Debit)->sum('amount');
            $net = $totalCredits - $totalDebits;

            $expected = (float) $account->initial_balance
                + (float) $account->transactions()->where('type', TransactionType::Credit)->sum('amount')
                - (float) $account->transactions()->where('type', TransactionType::Debit)->sum('amount');

            return [
                'reconciled_count' => $transactions->count(),
                'total_credits' => $totalCredits,
                'total_debits' => $totalDebits,
                'net' => $net,
                'balance_discrepancy' => (float) $account->current_balance - $expected,
                'account_balance' => (float) $account->current_balance,
            ];
        });
    }

    private function generateAccountCode(int $organisationId): string
    {
        $count = Account::withTrashed()
            ->where('organisation_id', $organisationId)
            ->count();

        return 'CPT-'.str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);
    }
}
