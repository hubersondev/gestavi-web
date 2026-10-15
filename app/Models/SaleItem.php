<?php

namespace App\Models;

use App\Enums\StockMovementReason;
use App\Exceptions\CommercialException;
use App\Exceptions\StockException;
use App\Models\Concerns\RecalculatesParentTotals;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use HasFactory, RecalculatesParentTotals;

    protected $fillable = [
        'sale_id', 'flock_id', 'product_id', 'is_live_birds',
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
            if (! static::isParentRecalculationSuspended()) {
                $item->sale?->calculateTotals();
            }

            $item->syncFlockQuantities();
        });

        // Stock suivi sur created/updated : `saved` ne distingue pas création et modification
        static::created(function (SaleItem $item): void {
            $item->syncStockMovement(created: true);
        });

        static::updated(function (SaleItem $item): void {
            $item->syncStockMovement();
        });

        static::deleted(function (SaleItem $item): void {
            if (! static::isParentRecalculationSuspended()) {
                $item->sale?->calculateTotals();
            }

            $item->syncFlockQuantities();
            $item->restock();
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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

    /**
     * Une ligne d'un produit suivi (œufs, carcasses…) sort du stock de la ferme qui vend, à la
     * création comme à la modification (l'écart seulement). Stock insuffisant → vente refusée.
     */
    public function syncStockMovement(bool $created = false): void
    {
        $sale = $this->sale;

        if (! $sale || $sale->isCancelled()) {
            return;
        }

        $previousProductId = $created ? null : $this->getOriginal('product_id');
        $previousQuantity = $created ? 0.0 : (float) $this->getOriginal('quantity');

        if ($previousProductId !== null && (int) $previousProductId !== (int) $this->product_id) {
            // Changement de produit : l'ancien revient en stock, le nouveau en sort
            $this->moveStock(Product::query()->find($previousProductId), $sale, -$previousQuantity);
            $this->moveStock($this->product, $sale, (float) $this->quantity);

            return;
        }

        $this->moveStock($this->product, $sale, (float) $this->quantity - $previousQuantity);
    }

    /**
     * Les unités d'une ligne supprimée (hors annulation de facture) reviennent en stock.
     */
    public function restock(): void
    {
        $sale = $this->sale;

        if (! $sale || $sale->isCancelled()) {
            return;
        }

        $this->moveStock($this->product, $sale, -(float) $this->quantity);
    }

    /**
     * Sortie (delta > 0) ou retour (delta < 0) du stock suivi par le produit, en unités de vente.
     */
    public function moveStock(?Product $product, Sale $sale, float $deltaSold): void
    {
        if (! $product || ! $product->tracksStock() || abs($deltaSold) < 0.0001) {
            return;
        }

        $stock = $product->resolveStockFor($sale->farm_id);

        if (! $stock) {
            return;
        }

        $units = $product->stockUnitsFor(abs($deltaSold));

        if ($deltaSold > 0) {
            try {
                $stock->recordOut(
                    quantity: $units,
                    reason: StockMovementReason::Sale,
                    flockId: $this->flock_id,
                    reference: $sale->invoice_number,
                    notes: "Vente de {$deltaSold} {$product->unit} : {$product->name}",
                );
            } catch (StockException) {
                throw CommercialException::insufficientStock(
                    $product->name,
                    $deltaSold,
                    $product->sellableQuantityFrom((float) $stock->fresh()->quantity),
                    $product->unit,
                    (float) $stock->fresh()->quantity,
                    $stock->unit,
                );
            }

            return;
        }

        $stock->recordIn(
            quantity: $units,
            reason: StockMovementReason::Return,
            flockId: $this->flock_id,
            reference: $sale->invoice_number,
            notes: "Retour en stock : {$product->name}",
        );
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
