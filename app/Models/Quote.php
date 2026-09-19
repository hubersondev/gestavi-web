<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quote extends Model
{
    use HasFactory, LogsAuditTrail, SoftDeletes;

    protected $fillable = [
        'organisation_id', 'farm_id',
        'customer_id', 'user_id', 'converted_sale_id',
        'quote_number', 'quote_date', 'valid_until', 'reference',
        'client_name', 'client_phone', 'client_email', 'client_address',
        'subtotal', 'discount_percent', 'discount_amount',
        'tax_percent', 'tax_amount', 'total',
        'status',
        'terms_conditions', 'notes', 'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'quote_date' => 'date',
            'valid_until' => 'date',
            'status' => QuoteStatus::class,
            'subtotal' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Quote $quote): void {
            if ($quote->organisation_id === null && $quote->farm_id !== null) {
                $quote->organisation_id = Farm::find($quote->farm_id)?->organisation_id;
            }

            if (empty($quote->quote_number) && $quote->organisation_id) {
                $quote->quote_number = self::generateQuoteNumber($quote->organisation_id);
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function convertedSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'converted_sale_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    // ─── SCOPES ──────────────────────────────────────────────────────────────

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', QuoteStatus::Draft);
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', QuoteStatus::Sent);
    }

    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', QuoteStatus::Accepted);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [QuoteStatus::Draft, QuoteStatus::Sent])
            ->whereNotNull('valid_until')
            ->where('valid_until', '<', now());
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === QuoteStatus::Draft;
    }

    public function isAccepted(): bool
    {
        return $this->status === QuoteStatus::Accepted;
    }

    public function isConverted(): bool
    {
        return $this->status === QuoteStatus::Converted;
    }

    public function isExpired(): bool
    {
        return $this->valid_until
            && $this->valid_until->isPast()
            && in_array($this->status, [QuoteStatus::Draft, QuoteStatus::Sent], true);
    }

    public function canBeConvertedToSale(): bool
    {
        return $this->isAccepted() && $this->converted_sale_id === null;
    }

    // ─── ACTIONS ─────────────────────────────────────────────────────────────

    public function calculateTotals(): void
    {
        $subtotal = (float) $this->items()->sum('total');
        $discountAmount = (float) $this->discount_percent > 0
            ? $subtotal * ((float) $this->discount_percent / 100)
            : (float) ($this->discount_amount ?? 0);
        $afterDiscount = $subtotal - $discountAmount;
        $taxAmount = (float) $this->tax_percent > 0
            ? $afterDiscount * ((float) $this->tax_percent / 100)
            : 0;

        $this->update([
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'total' => $afterDiscount + $taxAmount,
        ]);
    }

    public static function generateQuoteNumber(int $organisationId): string
    {
        $year = now()->year;
        $sequence = app(\App\Services\NumberSequence::class);

        return $sequence->nextFormatted(
            prefix: 'DEV-',
            key: 'quote',
            scopeId: $organisationId,
            year: $year,
            seedUsing: fn (): int => $sequence->maxSuffix('quotes', 'quote_number', "DEV-{$year}-", ['organisation_id' => $organisationId]),
        );
    }
}
