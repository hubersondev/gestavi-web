<?php

namespace App\Services;

use App\Enums\TransactionCategory;
use App\Enums\TransactionType;
use App\Exceptions\TransactionException;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    /**
     * Crée une transaction d'ajustement manuel (frais bancaires, intérêts, corrections).
     */
    public function createManualAdjustment(
        Account $account,
        TransactionType $type,
        float $amount,
        string $description,
        ?string $notes = null,
    ): Transaction {
        if ($amount <= 0) {
            throw TransactionException::invalidAmount();
        }

        if (! $account->is_active) {
            throw TransactionException::accountNotActive($account->name);
        }

        return DB::transaction(function () use ($account, $type, $amount, $description, $notes): Transaction {
            $transaction = $type === TransactionType::Credit
                ? $account->credit(
                    amount: $amount,
                    description: $description,
                    category: TransactionCategory::Adjustment,
                )
                : $account->debit(
                    amount: $amount,
                    description: $description,
                    category: TransactionCategory::Adjustment,
                );

            if ($notes) {
                $transaction->update(['notes' => $notes]);
            }

            return $transaction;
        });
    }

    /**
     * Contrepasse une transaction en créant une écriture opposée.
     */
    public function reverseTransaction(Transaction $transaction, string $reason): Transaction
    {
        if ($transaction->isReconciled()) {
            throw TransactionException::cannotReverseReconciled($transaction->id);
        }

        $account = $transaction->account;

        if (! $account->is_active) {
            throw TransactionException::accountNotActive($account->name);
        }

        return DB::transaction(function () use ($transaction, $account, $reason): Transaction {
            $description = "Contrepassation #{$transaction->id} — {$reason}";

            $reversal = $transaction->isCredit()
                ? $account->debit(
                    amount: (float) $transaction->amount,
                    description: $description,
                    category: TransactionCategory::Adjustment,
                    referenceType: Transaction::class,
                    referenceId: $transaction->id,
                )
                : $account->credit(
                    amount: (float) $transaction->amount,
                    description: $description,
                    category: TransactionCategory::Adjustment,
                    referenceType: Transaction::class,
                    referenceId: $transaction->id,
                );

            $reversal->update(['notes' => "Contrepassation de la transaction #{$transaction->id}"]);
            $transaction->update(['notes' => "Contrepassée par la transaction #{$reversal->id}"]);

            return $reversal;
        });
    }

    /**
     * Recherche toutes les transactions liées à un objet métier par référence polymorphe.
     */
    public function findByReference(string $referenceType, int $referenceId): Collection
    {
        return Transaction::query()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Vérifie l'intégrité du solde d'un compte et peut le corriger.
     *
     * @return array{
     *     account_id: int,
     *     account_name: string,
     *     expected_balance: float,
     *     actual_balance: float,
     *     discrepancy: float,
     *     has_discrepancy: bool,
     *     was_fixed: bool,
     * }
     */
    public function verifyAccountIntegrity(Account $account, bool $autoFix = false): array
    {
        $expected = (float) $account->initial_balance
            + (float) $account->transactions()->where('type', TransactionType::Credit)->sum('amount')
            - (float) $account->transactions()->where('type', TransactionType::Debit)->sum('amount');

        $actual = (float) $account->current_balance;
        $discrepancy = $actual - $expected;
        $hasDiscrepancy = abs($discrepancy) > 0.01;

        $wasFixed = false;

        if ($autoFix && $hasDiscrepancy) {
            $account->recalculateBalance();
            $wasFixed = true;
        }

        return [
            'account_id' => $account->id,
            'account_name' => $account->name,
            'expected_balance' => $expected,
            'actual_balance' => $actual,
            'discrepancy' => $discrepancy,
            'has_discrepancy' => $hasDiscrepancy,
            'was_fixed' => $wasFixed,
        ];
    }

    /**
     * Vérifie l'intégrité de tous les comptes d'une organisation.
     *
     * @return array{
     *     organisation_id: int,
     *     accounts_checked: int,
     *     discrepancies_found: int,
     *     fixed_count: int,
     *     total_discrepancy: float,
     *     details: array<int, array<string, mixed>>,
     *     checked_at: string,
     * }
     */
    public function verifyOrganisationIntegrity(int $organisationId, bool $autoFix = false): array
    {
        $accounts = Account::query()
            ->where('organisation_id', $organisationId)
            ->get();

        $details = $accounts
            ->map(fn (Account $account) => $this->verifyAccountIntegrity($account, $autoFix))
            ->all();

        return [
            'organisation_id' => $organisationId,
            'accounts_checked' => count($details),
            'discrepancies_found' => collect($details)->where('has_discrepancy', true)->count(),
            'fixed_count' => collect($details)->where('was_fixed', true)->count(),
            'total_discrepancy' => (float) collect($details)->sum('discrepancy'),
            'details' => $details,
            'checked_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Détecte les transactions potentiellement en doublon.
     *
     * @return array<int, array{
     *     account_id: int,
     *     amount: float,
     *     category: string,
     *     type: string,
     *     transaction_ids: array<int>,
     * }>
     */
    public function detectPotentialDuplicates(int $organisationId, int $daysWindow = 2): array
    {
        $candidates = Transaction::query()
            ->whereHas('account', fn ($q) => $q->where('organisation_id', $organisationId))
            ->where('transaction_date', '>=', now()->subDays($daysWindow * 30)->toDateString())
            ->orderBy('account_id')
            ->orderBy('amount')
            ->orderBy('transaction_date')
            ->get();

        $duplicates = [];

        $candidates->groupBy(fn (Transaction $t) => $t->account_id.'|'.$t->amount.'|'.$t->category?->value.'|'.$t->type?->value)
            ->each(function (Collection $group) use (&$duplicates, $daysWindow): void {
                if ($group->count() < 2) {
                    return;
                }

                $sorted = $group->sortBy('transaction_date')->values();
                $ids = [];

                foreach ($sorted as $i => $transaction) {
                    if ($i === 0) {
                        $ids[] = $transaction->id;

                        continue;
                    }

                    $previous = $sorted[$i - 1];
                    $delta = $transaction->transaction_date->diffInDays($previous->transaction_date);

                    if ($delta <= $daysWindow) {
                        $ids[] = $transaction->id;
                    }
                }

                if (count($ids) >= 2) {
                    $first = $sorted->first();
                    $duplicates[] = [
                        'account_id' => $first->account_id,
                        'amount' => (float) $first->amount,
                        'category' => $first->category?->value ?? '',
                        'type' => $first->type?->value ?? '',
                        'transaction_ids' => $ids,
                    ];
                }
            });

        return $duplicates;
    }
}
