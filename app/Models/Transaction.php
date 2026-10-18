<?php

namespace App\Models;

use App\Enums\BankStatementLineStatus;
use App\Enums\TransactionCategory;
use App\Enums\TransactionType;
use App\Exceptions\TransactionException;
use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\LogsAuditTrail;
use App\Services\TheftAlert;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;

class Transaction extends Model
{
    protected static function booted(): void
    {
        static::created(function (Transaction $transaction): void {
            if ($transaction->category !== TransactionCategory::Theft) {
                return;
            }

            $account = $transaction->account;

            TheftAlert::dispatch(
                (int) ($transaction->organisation_id ?? $account?->organisation_id),
                'Vol ou perte de caisse : '.TheftAlert::number((float) $transaction->amount).' XOF',
                [
                    '• Compte '.($account?->name ?? '?').($account?->farm ? ' — '.$account->farm->name : ''),
                    '• '.$transaction->description,
                    sprintf('• Solde après : %s XOF', TheftAlert::number((float) $transaction->balance_after)),
                ],
            );
        });
    }

    use BelongsToOrganisation, HasFactory, LogsAuditTrail;

    /** Parent métier dont héritent organisation_id et farm_id. */
    protected static string $organisationParentRelation = 'account';

    protected $fillable = [
        'organisation_id', 'farm_id',
        'account_id', 'user_id',
        'type', 'category',
        // balance_before / balance_after sont calculés par Account::recordMovement (forceFill), jamais assignés en masse
        'amount',
        'reference_type', 'reference_id', 'reference_number',
        'description', 'notes',
        'transaction_date', 'is_reconciled', 'reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'category' => TransactionCategory::class,
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'transaction_date' => 'date',
            'is_reconciled' => 'boolean',
            'reconciled_at' => 'datetime',
        ];
    }

    // ─── RELATIONS ───────────────────────────────────────────────────────────

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    // ─── SCOPES ──────────────────────────────────────────────────────────────

    public function scopeCredit(Builder $query): Builder
    {
        return $query->where('type', TransactionType::Credit);
    }

    public function scopeDebit(Builder $query): Builder
    {
        return $query->where('type', TransactionType::Debit);
    }

    public function scopeReconciled(Builder $query): Builder
    {
        return $query->where('is_reconciled', true);
    }

    public function scopeNotReconciled(Builder $query): Builder
    {
        return $query->where('is_reconciled', false);
    }

    public function scopeForCategory(Builder $query, TransactionCategory|string $category): Builder
    {
        return $query->where('category', $category instanceof TransactionCategory ? $category->value : $category);
    }

    public function scopeForDateRange(Builder $query, \DateTimeInterface $start, \DateTimeInterface $end): Builder
    {
        return $query->whereBetween('transaction_date', [$start, $end]);
    }

    public function scopeForMonth(Builder $query, int $year, int $month): Builder
    {
        return $query
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month);
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    public function isCredit(): bool
    {
        return $this->type === TransactionType::Credit;
    }

    public function isDebit(): bool
    {
        return $this->type === TransactionType::Debit;
    }

    public function isReconciled(): bool
    {
        return (bool) $this->is_reconciled;
    }

    public function getSignedAmount(): float
    {
        return $this->isCredit() ? (float) $this->amount : -((float) $this->amount);
    }

    /** Rapprochement bancaire dans lequel la transaction a été pointée. */
    public function bankReconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class)->withoutGlobalScopes();
    }

    // ─── ACTIONS ─────────────────────────────────────────────────────────────

    /**
     * Pointe la transaction, éventuellement dans un rapprochement par relevé. Les colonnes de
     * pointage sont les seules que le journal intangible laisse modifier.
     */
    public function reconcile(?BankReconciliation $reconciliation = null): void
    {
        $this->forceFill([
            'is_reconciled' => true,
            'reconciled_at' => now(),
            'bank_reconciliation_id' => $reconciliation?->getKey() ?? $this->bank_reconciliation_id,
        ])->save();
    }

    /**
     * Dépointe la transaction ; refusé si son rapprochement est clôturé. Une ligne de relevé qui
     * lui était associée redevient à rapprocher.
     */
    public function unreconcile(): void
    {
        if ($this->bank_reconciliation_id && $this->bankReconciliation?->isCompleted()) {
            throw TransactionException::lockedByReconciliation($this->id);
        }

        DB::transaction(function (): void {
            BankStatementLine::query()
                ->where('matched_transaction_id', $this->getKey())
                ->update(['matched_transaction_id' => null, 'status' => BankStatementLineStatus::Unmatched->value]);

            $this->forceFill([
                'is_reconciled' => false,
                'reconciled_at' => null,
                'bank_reconciliation_id' => null,
            ])->save();
        });
    }

    /** Pointée dans un rapprochement par relevé (dépointage depuis ce rapprochement uniquement). */
    public function belongsToBankReconciliation(): bool
    {
        return $this->bank_reconciliation_id !== null;
    }
}
