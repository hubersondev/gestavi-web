<?php

namespace App\Models;

use App\Enums\ProductKind;
use App\Enums\SaleStatus;
use App\Enums\StockCategoryType;
use App\Enums\StockLotStatus;
use App\Enums\StockMovementReason;
use App\Enums\StockMovementType;
use App\Exceptions\StockException;
use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\LogsAuditTrail;
use App\Services\NumberSequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Stock extends Model
{
    use BelongsToOrganisation, HasFactory, LogsAuditTrail, SoftDeletes;

    protected $fillable = [
        'organisation_id', 'farm_id', 'stock_category_id', 'kind',
        'name', 'code', 'description',
        'unit', 'quantity', 'min_quantity', 'unit_price',
        'expiry_date', 'location', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'min_quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'expiry_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Stock $stock): void {
            if ($stock->organisation_id === null && $stock->farm_id !== null) {
                $stock->organisation_id = Farm::find($stock->farm_id)?->organisation_id;
            }

            if (empty($stock->code) && $stock->organisation_id) {
                $stock->code = self::generateCode($stock->organisation_id);
            }
        });
    }

    // ─── STOCK D'ŒUFS ────────────────────────────────────────────────────────

    public const KIND_EGGS = 'eggs';

    /**
     * Stock d'œufs de la ferme, créé au premier besoin : catégorie « Production » de l'organisation,
     * unité « œuf », et reprise des œufs déjà collectés moins ceux déjà vendus avant le suivi.
     */
    public static function eggStockFor(int $farmId): self
    {
        $existing = static::query()
            ->where('farm_id', $farmId)
            ->where('kind', self::KIND_EGGS)
            ->first();

        if ($existing) {
            return $existing;
        }

        $farm = Farm::query()->findOrFail($farmId);

        return DB::transaction(function () use ($farm): self {
            $category = StockCategory::query()->firstOrCreate(
                ['organisation_id' => $farm->organisation_id, 'type' => StockCategoryType::Production->value],
                ['name' => 'Production', 'description' => 'Œufs et produits issus de la ferme'],
            );

            $stock = static::query()->create([
                'organisation_id' => $farm->organisation_id,
                'farm_id' => $farm->id,
                'stock_category_id' => $category->id,
                'kind' => self::KIND_EGGS,
                'name' => 'Œufs',
                'unit' => 'œuf',
                'quantity' => 0,
                'min_quantity' => 0,
                'unit_price' => 0,
                'is_active' => true,
            ]);

            $opening = self::eggsOnHandFromHistory($farm->id);

            if ($opening > 0) {
                $stock->recordIn(
                    quantity: $opening,
                    reason: StockMovementReason::Production,
                    reference: 'Reprise',
                    notes: 'Œufs collectés moins vendus avant la mise en place du suivi de stock',
                );
            }

            return $stock;
        });
    }

    /**
     * Œufs collectés (moins cassés) dans les relevés, moins ceux déjà vendus, pour une ferme.
     */
    public static function eggsOnHandFromHistory(int $farmId): float
    {
        $collected = (float) DailyRecord::query()
            ->where('farm_id', $farmId)
            ->selectRaw('COALESCE(SUM(GREATEST(COALESCE(eggs_collected, 0) - COALESCE(eggs_broken, 0), 0)), 0) AS net')
            ->value('net');

        $sold = (float) SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.farm_id', $farmId)
            ->where('sales.status', '!=', SaleStatus::Cancelled->value)
            ->whereIn('products.kind', [ProductKind::EggsTray->value, ProductKind::EggsUnit->value])
            ->selectRaw('COALESCE(SUM(sale_items.quantity * products.stock_units_per_unit), 0) AS sold')
            ->value('sold');

        return max(0, round($collected - $sold, 2));
    }

    /**
     * Un stock d'œufs pour chaque ferme (migration et nouvelles fermes).
     */
    public static function ensureEggStocksForAllFarms(): void
    {
        Farm::query()->pluck('id')->each(fn (int $farmId) => self::eggStockFor($farmId));
    }

    public function isEggStock(): bool
    {
        return $this->kind === self::KIND_EGGS;
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(StockCategory::class, 'stock_category_id');
    }

    public function lots(): HasMany
    {
        return $this->hasMany(StockLot::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    // ─── SCOPES ──────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('quantity', '<=', 'min_quantity')
            ->where('min_quantity', '>', 0);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now());
    }

    public function scopeForOrganisation(Builder $query, int $organisationId): Builder
    {
        return $query->where('organisation_id', $organisationId);
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    public function isLowStock(): bool
    {
        return (float) $this->min_quantity > 0 && (float) $this->quantity <= (float) $this->min_quantity;
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function hasStock(): bool
    {
        return (float) $this->quantity > 0;
    }

    public function getTotalValue(): float
    {
        return (float) $this->quantity * (float) $this->unit_price;
    }

    // ─── ACTIONS ─────────────────────────────────────────────────────────────

    /**
     * Enregistre une entrée de stock et crée le mouvement correspondant.
     */
    public function recordIn(
        float $quantity,
        StockMovementReason $reason = StockMovementReason::Purchase,
        ?float $unitPrice = null,
        ?int $stockLotId = null,
        ?int $flockId = null,
        ?string $reference = null,
        ?string $notes = null,
    ): StockMovement {
        if ($quantity <= 0) {
            throw StockException::invalidQuantity();
        }

        return DB::transaction(function () use ($quantity, $reason, $unitPrice, $stockLotId, $flockId, $reference, $notes): StockMovement {
            /** @var Stock $locked */
            $locked = static::query()->lockForUpdate()->findOrFail($this->getKey());

            $movement = $locked->movements()->create([
                'stock_lot_id' => $stockLotId,
                'user_id' => auth()->id(),
                'flock_id' => $flockId,
                'type' => StockMovementType::In,
                'reason' => $reason,
                'quantity' => $quantity,
                'unit_price' => $unitPrice ?? $locked->unit_price,
                'total_price' => $quantity * ($unitPrice ?? (float) $locked->unit_price),
                'movement_date' => now()->toDateString(),
                'reference' => $reference,
                'notes' => $notes,
            ]);

            // Valorisation au coût moyen unitaire pondéré (CMUP) quand un prix d'entrée est connu
            $attributes = ['quantity' => (float) $locked->quantity + $quantity];

            if ($unitPrice !== null) {
                $attributes['unit_price'] = self::weightedAverageCost(
                    (float) $locked->quantity,
                    (float) $locked->unit_price,
                    $quantity,
                    $unitPrice,
                );
            }

            $locked->update($attributes);
            $this->syncQuantityFrom($locked);

            return $movement;
        });
    }

    /**
     * CMUP = (quantité avant × prix avant + quantité entrée × prix entrée) / quantité après.
     * Un stock nul ou négatif avant l'entrée prend simplement le prix d'entrée.
     */
    public static function weightedAverageCost(float $quantityBefore, float $priceBefore, float $quantityIn, float $priceIn): float
    {
        $quantityAfter = $quantityBefore + $quantityIn;

        if ($quantityBefore <= 0 || $quantityAfter <= 0) {
            return round($priceIn, 2);
        }

        return round((($quantityBefore * $priceBefore) + ($quantityIn * $priceIn)) / $quantityAfter, 2);
    }

    /**
     * Enregistre une sortie de stock (consommation/vente/perte) et débite la quantité.
     */
    public function recordOut(
        float $quantity,
        StockMovementReason $reason = StockMovementReason::Consumption,
        ?int $flockId = null,
        ?string $reference = null,
        ?string $notes = null,
    ): StockMovement {
        if ($quantity <= 0) {
            throw StockException::invalidQuantity();
        }

        return DB::transaction(function () use ($quantity, $reason, $flockId, $reference, $notes): StockMovement {
            // La disponibilité est vérifiée sur la ligne verrouillée, pas sur l'instance en mémoire
            /** @var Stock $locked */
            $locked = static::query()->lockForUpdate()->findOrFail($this->getKey());

            if ((float) $locked->quantity < $quantity) {
                throw StockException::insufficientStock($locked->name, $quantity, (float) $locked->quantity);
            }

            $movement = $locked->movements()->create([
                'user_id' => auth()->id(),
                'flock_id' => $flockId,
                'type' => StockMovementType::Out,
                'reason' => $reason,
                'quantity' => $quantity,
                'unit_price' => $locked->unit_price,
                'total_price' => $quantity * (float) $locked->unit_price,
                'movement_date' => now()->toDateString(),
                'reference' => $reference,
                'notes' => $notes,
            ]);

            $locked->decrement('quantity', $quantity);
            $this->syncQuantityFrom($locked);

            return $movement;
        });
    }

    /**
     * Aligne l'instance appelante sur la quantité réellement persistée.
     */
    protected function syncQuantityFrom(Stock $locked): void
    {
        $fresh = $locked->fresh();

        $this->quantity = $fresh->quantity;
        $this->unit_price = $fresh->unit_price;
        $this->syncOriginalAttributes(['quantity', 'unit_price']);
    }

    /**
     * Vrai si le produit possède des lots encore disponibles (sorties à consommer en FIFO/FEFO).
     */
    public function hasAvailableLots(): bool
    {
        return $this->lots()
            ->whereIn('status', [StockLotStatus::Available, StockLotStatus::Low])
            ->where('current_quantity', '>', 0)
            ->exists();
    }

    /**
     * Recalcule la quantité à partir des lots disponibles (utile pour la cohérence FIFO).
     */
    public function recalculateFromLots(): void
    {
        $this->update([
            'quantity' => (float) $this->lots()->sum('current_quantity'),
        ]);
    }

    public static function generateCode(int $organisationId): string
    {
        $sequence = app(NumberSequence::class);

        return $sequence->nextFormatted(
            prefix: 'STK-',
            key: 'stock',
            scopeId: $organisationId,
            seedUsing: fn (): int => $sequence->maxSuffix('stocks', 'code', 'STK-', ['organisation_id' => $organisationId]),
        );
    }
}
