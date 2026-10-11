<?php

namespace App\Models;

use App\Enums\StockMovementReason;
use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Abattage d'une partie (ou de la totalité) d'une bande : sortie de sujets,
 * entrée facultative de carcasses dans un stock de destination.
 */
class Slaughter extends Model
{
    use BelongsToOrganisation, LogsAuditTrail;
    use HasFactory;

    /** Parent métier dont héritent organisation_id et farm_id. */
    protected static string $organisationParentRelation = 'flock';

    protected $fillable = [
        'organisation_id', 'farm_id', 'flock_id',
        'destination_stock_id', 'stock_movement_id', 'recorded_by',
        'slaughter_date', 'birds_count', 'live_weight_kg', 'carcass_weight_kg', 'yield_percent',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'slaughter_date' => 'date',
            'birds_count' => 'integer',
            'live_weight_kg' => 'decimal:2',
            'carcass_weight_kg' => 'decimal:2',
            'yield_percent' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Slaughter $slaughter): void {
            $slaughter->recorded_by ??= auth()->id();
            $slaughter->assertFlockCanBeSlaughtered();
        });

        static::updating(function (Slaughter $slaughter): void {
            if ($slaughter->isDirty('birds_count')) {
                $slaughter->assertFlockCanBeSlaughtered((int) $slaughter->getOriginal('birds_count'));
            }
        });

        static::saving(function (Slaughter $slaughter): void {
            $live = (float) ($slaughter->live_weight_kg ?? 0);
            $carcass = (float) ($slaughter->carcass_weight_kg ?? 0);
            $slaughter->yield_percent = $live > 0 && $carcass > 0 ? round($carcass / $live * 100, 2) : null;
        });

        static::created(function (Slaughter $slaughter): void {
            $slaughter->stockCarcasses();
        });

        static::saved(function (Slaughter $slaughter): void {
            $slaughter->flock?->recalculateQuantities();
        });

        static::deleted(function (Slaughter $slaughter): void {
            $slaughter->flock?->recalculateQuantities();
        });
    }

    /**
     * Refuse un abattage supérieur à l'effectif présent ou pendant un délai d'attente.
     */
    protected function assertFlockCanBeSlaughtered(int $alreadyCounted = 0): void
    {
        $flock = Flock::query()->find($this->flock_id);

        if (! $flock) {
            return;
        }

        if ($flock->isUnderWithdrawal($this->slaughter_date)) {
            throw new \LogicException(sprintf(
                'La bande %s est sous délai d\'attente jusqu\'au %s : aucun abattage avant cette date.',
                $flock->code,
                $flock->getWithdrawalUntil()?->format('d/m/Y'),
            ));
        }

        $available = (int) $flock->quantity_current + $alreadyCounted;

        if ((int) $this->birds_count > $available) {
            throw new \InvalidArgumentException(sprintf(
                'Abattage de %d sujets refusé : la bande %s n\'en compte que %d.',
                (int) $this->birds_count,
                $flock->code,
                $available,
            ));
        }
    }

    /**
     * Entrée des carcasses dans le stock de destination (en kg si l'unité du stock est le kg, sinon en sujets).
     */
    protected function stockCarcasses(): void
    {
        if (! $this->destination_stock_id || $this->stock_movement_id) {
            return;
        }

        $stock = Stock::query()->find($this->destination_stock_id);

        if (! $stock) {
            return;
        }

        $quantity = mb_strtolower((string) $stock->unit) === 'kg' && (float) ($this->carcass_weight_kg ?? 0) > 0
            ? (float) $this->carcass_weight_kg
            : (float) $this->birds_count;

        DB::transaction(function () use ($stock, $quantity): void {
            $movement = $stock->recordIn(
                quantity: $quantity,
                reason: StockMovementReason::Return,
                flockId: $this->flock_id,
                reference: 'Abattage du '.$this->slaughter_date?->format('d/m/Y'),
                notes: "Abattage de {$this->birds_count} sujet(s) de la bande ".($this->flock?->code ?? ''),
            );

            static::withoutEvents(fn () => static::query()->whereKey($this->getKey())->update(['stock_movement_id' => $movement->id]));
            $this->stock_movement_id = $movement->id;
        });
    }

    public function flock(): BelongsTo
    {
        return $this->belongsTo(Flock::class);
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function destinationStock(): BelongsTo
    {
        return $this->belongsTo(Stock::class, 'destination_stock_id');
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
