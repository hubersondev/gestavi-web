<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\MobileMoneyOperator;
use App\Enums\TransactionCategory;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organisation_id', 'farm_id',
        'name', 'code', 'type',
        'bank_name', 'account_number', 'phone_number', 'operator',
        'initial_balance', 'current_balance', 'currency',
        'description', 'notes',
        'is_default', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'operator' => MobileMoneyOperator::class,
            'initial_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Account $account): void {
            if ($account->organisation_id === null && $account->farm_id !== null) {
                $account->organisation_id = Farm::find($account->farm_id)?->organisation_id;
            }
        });
    }

    // ─── RELATIONS ───────────────────────────────────────────────────────────

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(AccountTransfer::class, 'from_account_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(AccountTransfer::class, 'to_account_id');
    }

    // ─── SCOPES ──────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    public function scopeForOrganisation(Builder $query, int $organisationId): Builder
    {
        return $query->where('organisation_id', $organisationId);
    }

    public function scopeForFarm(Builder $query, int $farmId): Builder
    {
        return $query->where('farm_id', $farmId);
    }

    public function scopeOfType(Builder $query, AccountType|string $type): Builder
    {
        return $query->where('type', $type instanceof AccountType ? $type->value : $type);
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    public function isCash(): bool
    {
        return $this->type === AccountType::Cash;
    }

    public function isBank(): bool
    {
        return $this->type === AccountType::Bank;
    }

    public function isMobileMoney(): bool
    {
        return $this->type === AccountType::MobileMoney;
    }

    public function isDefault(): bool
    {
        return (bool) $this->is_default;
    }

    public function hasPositiveBalance(): bool
    {
        return (float) $this->current_balance > 0;
    }

    public function canDebit(float $amount): bool
    {
        return $this->is_active && (float) $this->current_balance >= $amount;
    }

    // ─── ACTIONS ─────────────────────────────────────────────────────────────

    public function credit(
        float $amount,
        string $description,
        TransactionCategory $category = TransactionCategory::Other,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $referenceNumber = null,
    ): Transaction {
        $balanceBefore = (float) $this->current_balance;
        $balanceAfter = $balanceBefore + $amount;

        $transaction = $this->transactions()->create([
            'user_id' => auth()->id(),
            'type' => TransactionType::Credit,
            'category' => $category,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reference_number' => $referenceNumber,
            'description' => $description,
            'transaction_date' => now()->toDateString(),
        ]);

        $this->update(['current_balance' => $balanceAfter]);

        return $transaction;
    }

    public function debit(
        float $amount,
        string $description,
        TransactionCategory $category = TransactionCategory::Other,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $referenceNumber = null,
    ): Transaction {
        $balanceBefore = (float) $this->current_balance;
        $balanceAfter = $balanceBefore - $amount;

        $transaction = $this->transactions()->create([
            'user_id' => auth()->id(),
            'type' => TransactionType::Debit,
            'category' => $category,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reference_number' => $referenceNumber,
            'description' => $description,
            'transaction_date' => now()->toDateString(),
        ]);

        $this->update(['current_balance' => $balanceAfter]);

        return $transaction;
    }

    public function getTotalCredits(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): float
    {
        return (float) $this->transactions()
            ->where('type', TransactionType::Credit)
            ->when($from, fn (Builder $q) => $q->where('transaction_date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('transaction_date', '<=', $to))
            ->sum('amount');
    }

    public function getTotalDebits(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): float
    {
        return (float) $this->transactions()
            ->where('type', TransactionType::Debit)
            ->when($from, fn (Builder $q) => $q->where('transaction_date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('transaction_date', '<=', $to))
            ->sum('amount');
    }

    public function getNetMovement(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): float
    {
        return $this->getTotalCredits($from, $to) - $this->getTotalDebits($from, $to);
    }

    public function recalculateBalance(): void
    {
        $credits = (float) $this->transactions()->where('type', TransactionType::Credit)->sum('amount');
        $debits = (float) $this->transactions()->where('type', TransactionType::Debit)->sum('amount');
        $this->update(['current_balance' => (float) $this->initial_balance + $credits - $debits]);
    }

    public function setAsDefault(): void
    {
        static::query()
            ->where('organisation_id', $this->organisation_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }
}
