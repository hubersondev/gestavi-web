<?php

namespace App\Models;

use App\Enums\VaccinationStatus;
use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vaccination extends Model
{
    use BelongsToOrganisation, HasFactory, LogsAuditTrail;

    /** Parent métier dont héritent organisation_id et farm_id. */
    protected static string $organisationParentRelation = 'flock';

    protected $fillable = [
        'organisation_id', 'farm_id',
        'flock_id',
        'health_program_id',
        'administered_by',
        'treatment_name',
        'treatment_type',
        'scheduled_date',
        'administered_date',
        'age_at_treatment',
        'administration_route',
        'dosage',
        'withdrawal_days',
        'withdrawal_until',
        'batch_number',
        'expiry_date',
        'birds_treated',
        'cost',
        'stock_id',
        'stock_quantity_used',
        'stock_movement_id',
        'status',
        'observations',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => VaccinationStatus::class,
            'scheduled_date' => 'date',
            'administered_date' => 'date',
            'expiry_date' => 'date',
            'withdrawal_days' => 'integer',
            'withdrawal_until' => 'date',
            'cost' => 'decimal:2',
        ];
    }

    // ===== RELATIONS =====

    public function flock(): BelongsTo
    {
        return $this->belongsTo(Flock::class);
    }

    public function healthProgram(): BelongsTo
    {
        return $this->belongsTo(HealthProgram::class);
    }

    public function administrator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administered_by');
    }

    /**
     * Produit de stock (vaccin, médicament) consommé lors de l'administration.
     */
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    // ===== SCOPES =====

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', VaccinationStatus::Pending);
    }

    public function scopeDone(Builder $query): Builder
    {
        return $query->where('status', VaccinationStatus::Done);
    }

    public function scopeSkipped(Builder $query): Builder
    {
        return $query->where('status', VaccinationStatus::Skipped);
    }

    public function scopeDelayed(Builder $query): Builder
    {
        return $query->where('status', VaccinationStatus::Delayed);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [VaccinationStatus::Pending, VaccinationStatus::Delayed])
            ->where('scheduled_date', '<', now()->toDateString());
    }

    public function scopeUpcoming(Builder $query, int $days = 7): Builder
    {
        return $query
            ->where('status', VaccinationStatus::Pending)
            ->whereBetween('scheduled_date', [now()->toDateString(), now()->addDays($days)->toDateString()]);
    }

    public function scopeVaccins(Builder $query): Builder
    {
        return $query->where('treatment_type', 'vaccin');
    }

    // ===== HELPERS =====

    public function isPending(): bool
    {
        return $this->status === VaccinationStatus::Pending;
    }

    public function isDone(): bool
    {
        return $this->status === VaccinationStatus::Done;
    }

    public function isSkipped(): bool
    {
        return $this->status === VaccinationStatus::Skipped;
    }

    public function isDelayed(): bool
    {
        return $this->status === VaccinationStatus::Delayed;
    }

    public function isOverdue(): bool
    {
        return $this->isPending() && $this->scheduled_date?->isPast();
    }

    public function isVaccin(): bool
    {
        return $this->treatment_type === 'vaccin';
    }

    public function isActionable(): bool
    {
        return $this->isPending() || $this->isDelayed();
    }

    /**
     * Nombre de jours avant la date prévue (négatif si en retard).
     */
    public function getDaysUntilScheduled(): ?int
    {
        if (! $this->scheduled_date) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->scheduled_date, false);
    }

    /**
     * Nombre de jours de retard.
     */
    public function getDaysOverdue(): int
    {
        if (! $this->isOverdue()) {
            return 0;
        }

        return (int) $this->scheduled_date->diffInDays(now());
    }

    /**
     * Coût par sujet traité.
     */
    public function getCostPerBird(): ?float
    {
        if (! $this->cost || ! $this->birds_treated || $this->birds_treated <= 0) {
            return null;
        }

        return round((float) $this->cost / $this->birds_treated, 2);
    }

    /**
     * Marquer la vaccination comme effectuée.
     */
    public function markAsDone(array $data = []): void
    {
        $administeredDate = \Carbon\Carbon::parse($data['administered_date'] ?? now()->toDateString());
        $withdrawalDays = (int) ($data['withdrawal_days'] ?? $this->withdrawal_days ?? 0);

        $stockId = $data['stock_id'] ?? null;
        $stockQuantity = (float) ($data['stock_quantity_used'] ?? 0);
        unset($data['stock_id'], $data['stock_quantity_used']);

        \Illuminate\Support\Facades\DB::transaction(function () use ($data, $administeredDate, $withdrawalDays, $stockId, $stockQuantity): void {
            $this->update(array_merge([
                'status' => VaccinationStatus::Done,
                'administered_date' => $administeredDate->toDateString(),
                // Délai d'attente : aucune vente ni abattage avant cette date
                'withdrawal_until' => $withdrawalDays > 0
                    ? $administeredDate->copy()->addDays($withdrawalDays)->toDateString()
                    : null,
            ], $data));

            // Déstockage du produit administré (vaccin, médicament) imputé à la bande
            if ($stockId && $stockQuantity > 0 && ! $this->stock_movement_id) {
                $this->consumeFromStock((int) $stockId, $stockQuantity);
            }
        });
    }

    /**
     * Prélève le produit sur le stock (FIFO par lots si disponibles) et mémorise le mouvement ;
     * le coût de la vaccination prend la valeur du mouvement s'il n'a pas été saisi.
     */
    public function consumeFromStock(int $stockId, float $quantity): void
    {
        $stock = Stock::query()->findOrFail($stockId);

        $movements = app(\App\Services\StockService::class)->recordOut(
            stock: $stock,
            quantity: $quantity,
            reason: \App\Enums\StockMovementReason::Consumption,
            flockId: $this->flock_id,
            reference: 'Vaccination #'.$this->getKey(),
            notes: "{$this->treatment_name} — bande ".($this->flock?->code ?? ''),
        );

        // Le coût du produit est porté par le mouvement de stock (valorisé au CMUP) : `cost` ne
        // reçoit que les frais hors stock saisis explicitement, sinon le coût de revient le compterait deux fois.
        $this->update([
            'stock_id' => $stock->id,
            'stock_quantity_used' => $quantity,
            'stock_movement_id' => $movements->first()?->id,
        ]);
    }

    /**
     * Valeur du produit prélevé sur le stock pour cette vaccination.
     */
    public function getStockValue(): float
    {
        return $this->stock_movement_id
            ? (float) StockMovement::query()->where('reference', 'Vaccination #'.$this->getKey())->sum('total_price')
            : 0.0;
    }

    /**
     * Vrai si le délai d'attente de ce traitement court encore.
     */
    public function isWithdrawalActive(): bool
    {
        return $this->isDone()
            && $this->withdrawal_until !== null
            && $this->withdrawal_until->endOfDay()->isFuture();
    }

    /**
     * Marquer la vaccination comme ignorée.
     */
    public function markAsSkipped(string $reason = ''): void
    {
        $this->update([
            'status' => VaccinationStatus::Skipped,
            'notes' => $reason ?: $this->notes,
        ]);
    }

    /**
     * Reporter la vaccination à une nouvelle date.
     */
    public function reschedule(\Carbon\Carbon $newDate, string $reason = ''): void
    {
        $this->update([
            'status' => VaccinationStatus::Delayed,
            'scheduled_date' => $newDate,
            'notes' => $reason ?: $this->notes,
        ]);
    }
}
