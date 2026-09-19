<?php

namespace App\Models;

use App\Enums\CustomerType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organisation_id', 'farm_id',
        'name', 'code', 'type',
        'phone', 'phone_secondary', 'email',
        'address', 'city_id',
        'credit_limit', 'balance',
        'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => CustomerType::class,
            'credit_limit' => 'decimal:2',
            'balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Customer $customer): void {
            if ($customer->organisation_id === null && $customer->farm_id !== null) {
                $customer->organisation_id = Farm::find($customer->farm_id)?->organisation_id;
            }

            if (empty($customer->code) && $customer->organisation_id) {
                $customer->code = self::generateCode($customer->organisation_id);
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

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, Sale::class);
    }

    // ─── SCOPES ──────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeWithBalance(Builder $query): Builder
    {
        return $query->where('balance', '>', 0);
    }

    public function scopeOverCreditLimit(Builder $query): Builder
    {
        return $query->whereColumn('balance', '>', 'credit_limit')
            ->where('credit_limit', '>', 0);
    }

    public function scopeForOrganisation(Builder $query, int $organisationId): Builder
    {
        return $query->where('organisation_id', $organisationId);
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    public function hasBalance(): bool
    {
        return (float) $this->balance > 0;
    }

    public function isOverCreditLimit(): bool
    {
        return (float) $this->credit_limit > 0 && (float) $this->balance > (float) $this->credit_limit;
    }

    public function getAvailableCredit(): float
    {
        if ((float) $this->credit_limit <= 0) {
            return 0;
        }

        return max(0, (float) $this->credit_limit - (float) $this->balance);
    }

    public function canPurchaseOnCredit(float $amount): bool
    {
        if ((float) $this->credit_limit <= 0) {
            return false;
        }

        return ((float) $this->balance + $amount) <= (float) $this->credit_limit;
    }

    public function addToBalance(float $amount): void
    {
        $this->increment('balance', $amount);
    }

    public function subtractFromBalance(float $amount): void
    {
        $this->decrement('balance', min($amount, (float) $this->balance));
    }

    public function getTotalPurchases(): float
    {
        return (float) $this->sales()->sum('total');
    }

    public function getTotalPaid(): float
    {
        return (float) $this->sales()->sum('paid');
    }

    public static function generateCode(int $organisationId): string
    {
        $sequence = app(\App\Services\NumberSequence::class);

        return $sequence->nextFormatted(
            prefix: 'CLI-',
            key: 'customer',
            scopeId: $organisationId,
            seedUsing: fn (): int => $sequence->maxSuffix('customers', 'code', 'CLI-', ['organisation_id' => $organisationId]),
        );
    }
}
