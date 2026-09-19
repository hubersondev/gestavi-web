<?php

namespace App\Models;

use App\Enums\StockLotStatus;
use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockLot extends Model
{
    use HasFactory, LogsAuditTrail, SoftDeletes;

    protected $fillable = [
        'stock_id', 'supplier_id',
        'lot_number', 'internal_lot_number',
        'manufacturing_date', 'expiry_date', 'reception_date', 'shelf_life_days',
        'initial_quantity', 'current_quantity', 'reserved_quantity',
        'unit_price', 'total_value',
        'status', 'location', 'certificate_number', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => StockLotStatus::class,
            'manufacturing_date' => 'date',
            'expiry_date' => 'date',
            'reception_date' => 'date',
            'initial_quantity' => 'decimal:2',
            'current_quantity' => 'decimal:2',
            'reserved_quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total_value' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // `saving` précède `creating` : la quantité courante est initialisée ici pour que
        // la valeur et le statut soient calculés sur une quantité renseignée.
        static::saving(function (StockLot $lot): void {
            $lot->current_quantity ??= $lot->initial_quantity;

            // La valeur du lot suit la quantité restante, pas la quantité initiale
            $lot->total_value = round((float) $lot->current_quantity * (float) $lot->unit_price, 2);
            $lot->status = $lot->resolveStatus();
        });
    }

    // ─── RELATIONS ───────────────────────────────────────────────────────────

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    // ─── SCOPES ──────────────────────────────────────────────────────────────

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', StockLotStatus::Available);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now());
    }

    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now(), now()->addDays($days)]);
    }

    public function scopeFifo(Builder $query): Builder
    {
        return $query->orderByRaw('expiry_date IS NULL, expiry_date ASC')
            ->orderBy('reception_date', 'asc');
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->expiry_date && $this->expiry_date->lte(now()->addDays($days));
    }

    public function isAvailable(): bool
    {
        return $this->status === StockLotStatus::Available;
    }

    public function isDepleted(): bool
    {
        return (float) $this->current_quantity <= 0;
    }

    public function getAvailableQuantity(): float
    {
        return max(0, (float) $this->current_quantity - (float) $this->reserved_quantity);
    }

    public function resolveStatus(): StockLotStatus
    {
        if ($this->isExpired()) {
            return StockLotStatus::Expired;
        }

        if ((float) $this->current_quantity <= 0) {
            return StockLotStatus::Depleted;
        }

        if ($this->stock && (float) $this->stock->min_quantity > 0
            && (float) $this->current_quantity <= (float) $this->stock->min_quantity) {
            return StockLotStatus::Low;
        }

        return $this->status instanceof StockLotStatus && $this->status === StockLotStatus::Blocked
            ? StockLotStatus::Blocked
            : StockLotStatus::Available;
    }
}
