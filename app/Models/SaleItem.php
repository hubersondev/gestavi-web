<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id', 'flock_id', 'is_live_birds',
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
        static::saving(function (SaleItem $item): void {
            $item->calculateTotal();
        });

        static::saved(function (SaleItem $item): void {
            $item->sale?->calculateTotals();
            $item->syncFlockQuantities();
        });

        static::deleted(function (SaleItem $item): void {
            $item->sale?->calculateTotals();
            $item->syncFlockQuantities();
        });
    }

    // ─── RELATIONS ───────────────────────────────────────────────────────────

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function flock(): BelongsTo
    {
        return $this->belongsTo(Flock::class);
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    /**
     * Une ligne « sujets vivants » modifie l'effectif de la bande liée
     * (y compris si la bande ou le drapeau a changé : on recalcule l'ancienne et la nouvelle).
     */
    public function syncFlockQuantities(): void
    {
        $flockIds = array_filter(array_unique([
            $this->flock_id,
            $this->getOriginal('flock_id'),
        ]));

        if ($flockIds === [] || (! $this->is_live_birds && ! $this->getOriginal('is_live_birds'))) {
            return;
        }

        Flock::query()->whereIn('id', $flockIds)->get()->each->recalculateQuantities();
    }

    public function calculateTotal(): void
    {
        $subtotal = (float) $this->quantity * (float) $this->unit_price;

        $discount = (float) $this->discount_percent > 0
            ? $subtotal * ((float) $this->discount_percent / 100)
            : (float) ($this->discount_amount ?? 0);

        $this->discount_amount = $discount;
        $this->total = max(0, $subtotal - $discount);
    }

    public function getSubtotal(): float
    {
        return (float) $this->quantity * (float) $this->unit_price;
    }

    public function hasDiscount(): bool
    {
        return (float) $this->discount_percent > 0 || (float) $this->discount_amount > 0;
    }
}
