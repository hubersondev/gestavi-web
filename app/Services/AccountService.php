<?php

namespace App\Services;

use App\Enums\AccountTransferStatus;
use App\Enums\TransactionCategory;
use App\Enums\TransactionType;
use App\Exceptions\AccountException;
use App\Models\Account;
use App\Models\AccountTransfer;
use App\Models\Transaction;
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
                $account->transactions()->create([
                    'user_id' => auth()->id(),
                    'type' => TransactionType::Credit,
                    'category' => TransactionCategory::Opening,
                    'amount' => $account->initial_balance,
                    'balance_before' => 0,
                    'balance_after' => $account->initial_balance,
                    'description' => "Solde d'ouverture du compte {$account->name}",
                    'transaction_date' => now()->toDateString(),
                ]);
            }

            return $account->fresh();
        });
    }

    /**
     * Effectue un transfert entre deux comptes de la même organisation.
     */
    public function executeTransfer(
        int $organisationId,
        int $fromAccountId,
        int $toAccountId,
        float $amount,
        float $fees = 0,
        ?string $description = null,
        ?string $notes = null,
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

        return DB::transaction(function () use ($organisationId, $fromAccount, $toAccount, $amount, $fees, $description, $notes): AccountTransfer {
            $transfer = AccountTransfer::create([
                'organisation_id' => $organisationId,
                'from_account_id' => $fromAccount->id,
                'to_account_id' => $toAccount->id,
                'user_id' => auth()->id(),
                'amount' => $amount,
                'fees' => $fees,
                'transfer_date' => now()->toDateString(),
                'status' => AccountTransferStatus::Pending,
                'description' => $description,
                'notes' => $notes,
            ]);

            $transfer->complete();

            return $transfer->fresh(['fromAccount', 'toAccount']);
        });
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
