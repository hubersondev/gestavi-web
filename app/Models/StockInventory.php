<?php

namespace App\Models;

use App\Enums\StockInventoryDifferenceReason;
use App\Enums\StockInventoryStatus;
use App\Enums\StockMovementReason;
use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class StockInventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'organisation_id', 'stock_id', 'stock_lot_id',
        'conducted_by', 'validated_by', 'adjustment_movement_id',
        'inventory_ref', 'inventory_date',
        'theoretical_quantity', 'physical_quantity', 'difference', 'difference_percent',
        'unit_price', 'difference_value',
        'status', 'difference_reason', 'justification', 'notes',
        'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => StockInventoryStatus::class,
            'difference_reason' => StockInventoryDifferenceReason::class,
            'inventory_date' => 'date',
            'theoretical_quantity' => 'decimal:2',
            'physical_quantity' => 'decimal:2',
            'difference' => 'decimal:2',
            'difference_percent' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'difference_value' => 'decimal:2',
            'validated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (StockInventory $inventory): void {
            if ($inventory->stock_id && $inventory->organisation_id === null) {
                $inventory->organisation_id = Stock::find($inventory->stock_id)?->organisation_id;
            }

            if (empty($inventory->inventory_ref) && $inventory->organisation_id) {
                $inventory->inventory_ref = self::generateReference($inventory->organisation_id);
            }

            $stock = Stock::find($inventory->stock_id);
            if ($stock) {
                $inventory->theoretical_quantity ??= (float) $stock->quantity;
                $inventory->unit_price ??= (float) $stock->unit_price;
            }
        });

        // Recalcule automatiquement difference, difference_percent et difference_value
        // à chaque save() (y compris update direct). Si vous mettez à jour physical_quantity
        // via un update Eloquent, ces champs seront recalculés automatiquement.
        static::saving(function (StockInventory $inventory): void {
            $theoretical = (float) $inventory->theoretical_quantity;
            $physical = (float) $inventory->physical_quantity;
            $inventory->difference = $physical - $theoretical;

            $inventory->difference_percent = $theoretical > 0
                ? round(($inventory->difference / $theoretical) * 100, 2)
                : null;

            $inventory->difference_value = (float) $inventory->unit_price * $inventory->difference;
        });
    }

    // ─── RELATIONS ───────────────────────────────────────────────────────────

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function stockLot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class);
    }

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conducted_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function adjustmentMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'adjustment_movement_id');
    }

    // ─── SCOPES ──────────────────────────────────────────────────────────────

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', StockInventoryStatus::Draft);
    }

    public function scopeAdjusted(Builder $query): Builder
    {
        return $query->where('status', StockInventoryStatus::Adjusted);
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === StockInventoryStatus::Draft;
    }

    public function isValidated(): bool
    {
        return $this->status === StockInventoryStatus::Validated;
    }

    public function isAdjusted(): bool
    {
        return $this->status === StockInventoryStatus::Adjusted;
    }

    public function hasDifference(): bool
    {
        return abs((float) $this->difference) > 0.01;
    }

    public function isPositiveDifference(): bool
    {
        return (float) $this->difference > 0;
    }

    public function canBeAdjusted(): bool
    {
        return in_array($this->status, [StockInventoryStatus::Draft, StockInventoryStatus::PendingValidation, StockInventoryStatus::Validated], true);
    }

    // ─── ACTIONS ─────────────────────────────────────────────────────────────

    /**
     * Applique l'écart au stock en créant un mouvement d'ajustement et met à jour
     * le statut en "adjusted". Idempotent : ne fait rien si déjà ajusté ou pas d'écart.
     */
    public function applyAdjustment(): void
    {
        if ($this->isAdjusted()) {
            return;
        }

        DB::transaction(function (): void {
            if (! $this->hasDifference()) {
                $this->update([
                    'status' => StockInventoryStatus::Adjusted,
                    'validated_by' => $this->validated_by ?? auth()->id(),
                    'validated_at' => $this->validated_at ?? now(),
                ]);

                return;
            }

            $stock = $this->stock;
            $diff = (float) $this->difference;
            $type = $diff > 0 ? StockMovementType::In : StockMovementType::Out;

            $movement = $stock->movements()->create([
                'stock_lot_id' => $this->stock_lot_id,
                'user_id' => auth()->id(),
                'type' => $type,
                'reason' => StockMovementReason::Adjustment,
                'quantity' => abs($diff),
                'unit_price' => $stock->unit_price,
                'total_price' => abs($diff) * (float) $stock->unit_price,
                'movement_date' => now()->toDateString(),
                'reference' => $this->inventory_ref,
                'notes' => 'Ajustement inventaire '.$this->inventory_ref.($this->justification ? ' — '.$this->justification : ''),
            ]);

            if ($diff > 0) {
                $stock->increment('quantity', abs($diff));
            } else {
                $stock->decrement('quantity', abs($diff));
            }

            $this->update([
                'adjustment_movement_id' => $movement->id,
                'status' => StockInventoryStatus::Adjusted,
                'validated_by' => $this->validated_by ?? auth()->id(),
                'validated_at' => $this->validated_at ?? now(),
            ]);
        });
    }

    public static function generateReference(int $organisationId): string
    {
        $year = now()->year;
        $count = static::query()
            ->where('organisation_id', $organisationId)
            ->whereYear('created_at', $year)
            ->count();

        return sprintf('INV-%d-%05d', $year, $count + 1);
    }
}
