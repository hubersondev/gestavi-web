<?php

namespace App\Models;

use App\Enums\FlockStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Flock extends Model
{
    use HasFactory;

    protected $fillable = [
        'building_id',
        'chicken_type_id',
        'breed',
        'supplier_id',
        'code',
        'name',
        'quantity_started',
        'quantity_current',
        'age_at_arrival',
        'start_date',
        'expected_end_date',
        'actual_end_date',
        'status',
        'purchase_price_per_unit',
        'total_purchase_price',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => FlockStatus::class,
            'start_date' => 'date',
            'expected_end_date' => 'date',
            'actual_end_date' => 'date',
            'purchase_price_per_unit' => 'decimal:2',
            'total_purchase_price' => 'decimal:2',
        ];
    }

    // ===== RELATIONS =====

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function chickenType(): BelongsTo
    {
        return $this->belongsTo(ChickenType::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function dailyRecords(): HasMany
    {
        return $this->hasMany(DailyRecord::class);
    }

    public function vaccinations(): HasMany
    {
        return $this->hasMany(Vaccination::class);
    }

    public function healthIncidents(): HasMany
    {
        return $this->hasMany(HealthIncident::class);
    }

    public function vetVisits(): HasMany
    {
        return $this->hasMany(VetVisit::class);
    }

    public function farmLogs(): HasMany
    {
        return $this->hasMany(FarmLog::class);
    }

    // ===== SCOPES =====

    public function scopePlanned(Builder $query): Builder
    {
        return $query->where('status', FlockStatus::Planned);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', FlockStatus::Active);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', FlockStatus::Completed);
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', FlockStatus::Cancelled);
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->whereIn('status', [FlockStatus::Planned, FlockStatus::Active]);
    }

    public function scopeForBuilding(Builder $query, int $buildingId): Builder
    {
        return $query->where('building_id', $buildingId);
    }

    // ===== HELPERS =====

    public function isPlanned(): bool
    {
        return $this->status === FlockStatus::Planned;
    }

    public function isActive(): bool
    {
        return $this->status === FlockStatus::Active;
    }

    public function isCompleted(): bool
    {
        return $this->status === FlockStatus::Completed;
    }

    public function isCancelled(): bool
    {
        return $this->status === FlockStatus::Cancelled;
    }

    /**
     * Âge actuel de la bande en jours depuis la mise en place.
     */
    public function getCurrentAgeInDays(): ?int
    {
        if (! $this->start_date) {
            return null;
        }

        $endDate = $this->actual_end_date ?? now();

        return (int) $this->start_date->diffInDays($endDate) + $this->age_at_arrival;
    }

    /**
     * Âge actuel en semaines.
     */
    public function getCurrentAgeInWeeks(): ?float
    {
        $days = $this->getCurrentAgeInDays();

        if ($days === null) {
            return null;
        }

        return round($days / 7, 1);
    }

    /**
     * Taux de mortalité en pourcentage.
     */
    public function getMortalityRate(): float
    {
        if ($this->quantity_started <= 0) {
            return 0;
        }

        $losses = $this->quantity_started - $this->quantity_current;

        return round(($losses / $this->quantity_started) * 100, 2);
    }

    /**
     * Nombre de sujets perdus.
     */
    public function getLossCount(): int
    {
        return max(0, $this->quantity_started - $this->quantity_current);
    }

    /**
     * Durée prévue en jours.
     */
    public function getExpectedDurationInDays(): ?int
    {
        if (! $this->start_date || ! $this->expected_end_date) {
            return null;
        }

        return (int) $this->start_date->diffInDays($this->expected_end_date);
    }

    /**
     * Durée réelle en jours (si terminée).
     */
    public function getActualDurationInDays(): ?int
    {
        if (! $this->start_date || ! $this->actual_end_date) {
            return null;
        }

        return (int) $this->start_date->diffInDays($this->actual_end_date);
    }

    /**
     * Coût par sujet actuel.
     */
    public function getCostPerCurrentUnit(): ?float
    {
        if (! $this->total_purchase_price || $this->quantity_current <= 0) {
            return null;
        }

        return round($this->total_purchase_price / $this->quantity_current, 2);
    }

    /**
     * Phase de production courante de la bande, selon son âge et son type de volaille.
     */
    public function getCurrentPhase(): ?ProductionPhase
    {
        $age = $this->getCurrentAgeInDays();

        if ($age === null || ! $this->chicken_type_id) {
            return null;
        }

        return ProductionPhase::where('chicken_type_id', $this->chicken_type_id)
            ->where('start_day', '<=', $age)
            ->where('end_day', '>=', $age)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Démarrer la bande.
     */
    public function start(): void
    {
        $this->update([
            'status' => FlockStatus::Active,
            'quantity_current' => $this->quantity_current ?: $this->quantity_started,
            'start_date' => $this->start_date ?? now(),
        ]);
    }

    /**
     * Terminer la bande.
     */
    public function complete(): void
    {
        $this->update([
            'status' => FlockStatus::Completed,
            'actual_end_date' => now(),
        ]);
    }

    /**
     * Annuler la bande.
     */
    public function cancel(): void
    {
        $this->update([
            'status' => FlockStatus::Cancelled,
            'actual_end_date' => now(),
        ]);
    }
}
