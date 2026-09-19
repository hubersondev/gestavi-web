<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quote_id', 'flock_id',
        'product_name', 'description', 'unit',
        'quantity', 'unit_price',
        'discount_percent', 'discount_amount', 'total',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (QuoteItem $item): void {
            $item->calculateTotal();
        });

        static::saved(function (QuoteItem $item): void {
            $item->quote?->calculateTotals();
        });

        static::deleted(function (QuoteItem $item): void {
            $item->quote?->calculateTotals();
        });
    }

    // ─── RELATIONS ───────────────────────────────────────────────────────────

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function flock(): BelongsTo
    {
        return $this->belongsTo(Flock::class);
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    public function calculateTotal(): void
    {
        $subtotal = (float) $this->quantity * (float) $this->unit_price;

        $discount = (float) $this->discount_percent > 0
            ? $subtotal * ((float) $this->discount_percent / 100)
            : (float) ($this->discount_amount ?? 0);

        $this->discount_amount = $discount;
        $this->total = max(0, $subtotal - $discount);
    }
}
