<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id', 'stock_id', 'stock_lot_id',
        'product_name', 'product_code', 'description', 'unit',
        'quantity_ordered', 'quantity_received',
        'unit_price',
        'discount_percent', 'discount_amount',
        'tax_percent', 'tax_amount',
        'total',
        'expected_date', 'status', 'sort_order', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'decimal:2',
            'quantity_received' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'expected_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PurchaseOrderItem $item): void {
            $item->calculateTotal();
        });

        static::saved(function (PurchaseOrderItem $item): void {
            $item->purchaseOrder?->calculateTotals();
        });

        static::deleted(function (PurchaseOrderItem $item): void {
            $item->purchaseOrder?->calculateTotals();
        });
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function stockLot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class);
    }

    public function calculateTotal(): void
    {
        $subtotal = (float) $this->quantity_ordered * (float) $this->unit_price;

        $discount = (float) $this->discount_percent > 0
            ? $subtotal * ((float) $this->discount_percent / 100)
            : (float) ($this->discount_amount ?? 0);

        $afterDiscount = $subtotal - $discount;
        $tax = (float) $this->tax_percent > 0
            ? $afterDiscount * ((float) $this->tax_percent / 100)
            : (float) ($this->tax_amount ?? 0);

        $this->discount_amount = $discount;
        $this->tax_amount = $tax;
        $this->total = max(0, $afterDiscount + $tax);
    }

    public function getQuantityPending(): float
    {
        return max(0, (float) $this->quantity_ordered - (float) $this->quantity_received);
    }

    public function isFullyReceived(): bool
    {
        return (float) $this->quantity_received >= (float) $this->quantity_ordered;
    }
}
