<?php

namespace App\Models;

use App\Enums\FlockStatus;
use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Flock extends Model
{
    use BelongsToOrganisation, HasFactory, LogsAuditTrail, SoftDeletes;

    /** Parent métier dont héritent organisation_id et farm_id. */
    protected static string $organisationParentRelation = 'building';

    protected $fillable = [
        'organisation_id', 'farm_id',
        'building_id',
        'chicken_type_id',
        'breed',
        'supplier_id',
        'code',
        'name',
        'quantity_started',
        'quantity_current',
        'quantity_sold',
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

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
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

    // ===== EFFECTIF DÉRIVÉ =====

    /**
     * Sujets vivants vendus (lignes de vente « sujets vivants » de factures non annulées).
     */
    public function getSoldCount(): int
    {
        return (int) SaleItem::query()
            ->where('flock_id', $this->id)
            ->where('is_live_birds', true)
            ->whereHas('sale', fn (Builder $q) => $q->where('status', '!=', \App\Enums\SaleStatus::Cancelled->value))
            ->sum('quantity');
    }

    /**
     * Mortalité cumulée (somme des relevés quotidiens).
     */
    public function getTotalMortality(): int
    {
        return (int) $this->dailyRecords()->sum('mortality');
    }

    /**
     * Réformes / tris cumulés (somme des relevés quotidiens).
     */
    public function getTotalCulls(): int
    {
        return (int) $this->dailyRecords()->sum('culls');
    }

    /**
     * Recalcule quantity_current et quantity_sold depuis les faits :
     * effectif initial − mortalité − réformes − sujets vendus.
     */
    public function recalculateQuantities(): void
    {
        $sold = $this->getSoldCount();
        $current = max(0, (int) $this->quantity_started - $this->getTotalMortality() - $this->getTotalCulls() - $sold);

        static::withoutEvents(fn () => static::query()
            ->whereKey($this->id)
            ->update(['quantity_current' => $current, 'quantity_sold' => $sold]));

        $this->quantity_current = $current;
        $this->quantity_sold = $sold;
        $this->syncOriginalAttributes(['quantity_current', 'quantity_sold']);
    }

    /**
     * Effectif présent à la fin d'une date donnée (pertes et ventes jusqu'à cette date incluse).
     */
    public function birdsPresentOn(mixed $date): int
    {
        $date = \Carbon\Carbon::parse($date)->toDateString();

        $losses = (int) $this->dailyRecords()
            ->whereDate('record_date', '<=', $date)
            ->selectRaw('COALESCE(SUM(mortality + culls), 0) AS losses')
            ->value('losses');

        $sold = (int) SaleItem::query()
            ->where('flock_id', $this->id)
            ->where('is_live_birds', true)
            ->whereHas('sale', fn (Builder $q) => $q
                ->where('status', '!=', \App\Enums\SaleStatus::Cancelled->value)
                ->whereDate('sale_date', '<=', $date))
            ->sum('quantity');

        return max(0, (int) $this->quantity_started - $losses - $sold);
    }

    /**
     * Taux de mortalité cumulé en % de l'effectif initial (les réformes et ventes ne sont pas des morts).
     */
    public function getMortalityRate(): float
    {
        if ($this->quantity_started <= 0) {
            return 0;
        }

        return round(($this->getTotalMortality() / $this->quantity_started) * 100, 2);
    }

    /**
     * Taux de réforme cumulé en % de l'effectif initial.
     */
    public function getCullRate(): float
    {
        if ($this->quantity_started <= 0) {
            return 0;
        }

        return round(($this->getTotalCulls() / $this->quantity_started) * 100, 2);
    }

    /**
     * Viabilité en % (100 − taux de mortalité).
     */
    public function getViabilityRate(): float
    {
        return round(100 - $this->getMortalityRate(), 2);
    }

    /**
     * Nombre de sujets perdus (mortalité + réformes).
     */
    public function getLossCount(): int
    {
        return $this->getTotalMortality() + $this->getTotalCulls();
    }

    // ===== DÉLAI D'ATTENTE =====

    /**
     * Date de fin du délai d'attente le plus lointain parmi les traitements administrés.
     */
    public function getWithdrawalUntil(): ?\Carbon\CarbonInterface
    {
        $date = $this->vaccinations()
            ->where('status', \App\Enums\VaccinationStatus::Done->value)
            ->whereNotNull('withdrawal_until')
            ->max('withdrawal_until');

        return $date ? \Carbon\Carbon::parse($date) : null;
    }

    /**
     * Vrai si un traitement récent interdit encore la vente ou l'abattage.
     */
    public function isUnderWithdrawal(mixed $onDate = null): bool
    {
        $until = $this->getWithdrawalUntil();

        if (! $until) {
            return false;
        }

        $reference = $onDate ? \Carbon\Carbon::parse($onDate) : now();

        return $until->endOfDay()->greaterThanOrEqualTo($reference->startOfDay());
    }

    /**
     * Orientation zootechnique du type de volaille (chair, pondeuse, reproducteur, mixte).
     */
    public function productionKind(): \App\Enums\ProductionKind
    {
        return $this->chickenType?->productionKind() ?? \App\Enums\ProductionKind::Mixed;
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

        return ProductionPhase::getPhaseForAge(
            $this->chicken_type_id,
            $age,
            $this->building?->farm?->organisation_id,
        );
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
