<?php

namespace App\Models;

use App\Enums\StockMovementReason;
use App\Enums\StockMovementType;
use App\Exceptions\StockException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Stock extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organisation_id', 'farm_id', 'stock_category_id',
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
            $movement = $this->movements()->create([
                'stock_lot_id' => $stockLotId,
                'user_id' => auth()->id(),
                'flock_id' => $flockId,
                'type' => StockMovementType::In,
                'reason' => $reason,
                'quantity' => $quantity,
                'unit_price' => $unitPrice ?? $this->unit_price,
                'total_price' => $quantity * ($unitPrice ?? (float) $this->unit_price),
                'movement_date' => now()->toDateString(),
                'reference' => $reference,
                'notes' => $notes,
            ]);

            $this->increment('quantity', $quantity);

            return $movement;
        });
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

        if ((float) $this->quantity < $quantity) {
            throw StockException::insufficientStock($this->name, $quantity, (float) $this->quantity);
        }

        return DB::transaction(function () use ($quantity, $reason, $flockId, $reference, $notes): StockMovement {
            $movement = $this->movements()->create([
                'user_id' => auth()->id(),
                'flock_id' => $flockId,
                'type' => StockMovementType::Out,
                'reason' => $reason,
                'quantity' => $quantity,
                'unit_price' => $this->unit_price,
                'total_price' => $quantity * (float) $this->unit_price,
                'movement_date' => now()->toDateString(),
                'reference' => $reference,
                'notes' => $notes,
            ]);

            $this->decrement('quantity', $quantity);

            return $movement;
        });
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
        $count = static::withTrashed()
            ->where('organisation_id', $organisationId)
            ->count();

        return 'STK-'.str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
    }
}
