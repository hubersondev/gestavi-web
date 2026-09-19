<?php

namespace App\Models;

use App\Enums\StockMovementReason;
use App\Enums\StockMovementType;
use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory, LogsAuditTrail;

    protected $fillable = [
        'stock_id', 'stock_lot_id', 'user_id', 'flock_id',
        'type', 'reason',
        'quantity', 'unit_price', 'total_price',
        'movement_date', 'reference', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'reason' => StockMovementReason::class,
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'movement_date' => 'date',
        ];
    }

    // ─── RELATIONS ───────────────────────────────────────────────────────────

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function stockLot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function flock(): BelongsTo
    {
        return $this->belongsTo(Flock::class);
    }

    // ─── SCOPES ──────────────────────────────────────────────────────────────

    public function scopeIn(Builder $query): Builder
    {
        return $query->where('type', StockMovementType::In);
    }

    public function scopeOut(Builder $query): Builder
    {
        return $query->where('type', StockMovementType::Out);
    }

    public function scopeForDateRange(Builder $query, \DateTimeInterface $start, \DateTimeInterface $end): Builder
    {
        return $query->whereBetween('movement_date', [$start, $end]);
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    public function isIn(): bool
    {
        return $this->type === StockMovementType::In;
    }

    public function isOut(): bool
    {
        return $this->type === StockMovementType::Out;
    }

    public function getSignedQuantity(): float
    {
        return $this->isIn() ? (float) $this->quantity : -((float) $this->quantity);
    }
}
