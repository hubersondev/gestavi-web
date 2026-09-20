<?php

namespace App\Models;

use App\Enums\TransactionCategory;
use App\Enums\TransactionType;
use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaction extends Model
{
    use BelongsToOrganisation, HasFactory, LogsAuditTrail;

    /** Parent métier dont héritent organisation_id et farm_id. */
    protected static string $organisationParentRelation = 'account';

    protected $fillable = [
        'organisation_id', 'farm_id',
        'account_id', 'user_id',
        'type', 'category',
        'amount', 'balance_before', 'balance_after',
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

    // ─── ACTIONS ─────────────────────────────────────────────────────────────

    public function reconcile(): void
    {
        $this->update([
            'is_reconciled' => true,
            'reconciled_at' => now(),
        ]);
    }

    public function unreconcile(): void
    {
        $this->update([
            'is_reconciled' => false,
            'reconciled_at' => null,
        ]);
    }
}
