<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthProgram extends Model
{
    use HasFactory;

    protected $fillable = [
        'organisation_id',
        'farm_id',
        'chicken_type_id',
        'treatment_name',
        'treatment_type',
        'scheduled_day',
        'administration_route',
        'dosage',
        'withdrawal_days',
        'is_mandatory',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_mandatory' => 'boolean',
            'withdrawal_days' => 'integer',
            'scheduled_day' => 'integer',
            'chicken_type_id' => 'integer',
        ];
    }

    // ===== RELATIONS =====

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function chickenType(): BelongsTo
    {
        return $this->belongsTo(ChickenType::class);
    }

    // ===== SCOPES =====

    /**
     * Programmes non liés à une ferme (applicables à toutes les fermes de l'organisation).
     */
    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('farm_id');
    }

    /**
     * Programmes applicables à une ferme donnée (les globaux + ceux de la ferme).
     */
    public function scopeForFarm(Builder $query, int $farmId): Builder
    {
        return $query->where(function (Builder $q) use ($farmId) {
            $q->whereNull('farm_id')
                ->orWhere('farm_id', $farmId);
        });
    }

    /**
     * Programmes applicables à un type de volaille donné (inclut les programmes sans type spécifique).
     * Passer null pour récupérer tous les programmes sans filtrer par type.
     */
    public function scopeForChickenType(Builder $query, ?int $chickenTypeId): Builder
    {
        if ($chickenTypeId === null) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($chickenTypeId) {
            $q->whereNull('chicken_type_id')
                ->orWhere('chicken_type_id', $chickenTypeId);
        });
    }

    public function scopeMandatory(Builder $query): Builder
    {
        return $query->where('is_mandatory', true);
    }

    public function scopeVaccins(Builder $query): Builder
    {
        return $query->where('treatment_type', 'vaccin');
    }

    public function scopeForDay(Builder $query, int $day): Builder
    {
        return $query->where('scheduled_day', $day);
    }

    public function scopeForDayRange(Builder $query, int $startDay, int $endDay): Builder
    {
        return $query->whereBetween('scheduled_day', [$startDay, $endDay]);
    }

    // ===== HELPERS =====

    public function isGlobal(): bool
    {
        return $this->farm_id === null;
    }

    public function appliesToAllTypes(): bool
    {
        return $this->chicken_type_id === null;
    }

    public function isVaccin(): bool
    {
        return $this->treatment_type === 'vaccin';
    }

    public function isVermifuge(): bool
    {
        return $this->treatment_type === 'vermifuge';
    }

    public function isAnticoccidien(): bool
    {
        return $this->treatment_type === 'anticoccidien';
    }

    public function isVitamine(): bool
    {
        return $this->treatment_type === 'vitamine';
    }

    public function isAntibiotique(): bool
    {
        return $this->treatment_type === 'antibiotique';
    }

    public function isMandatory(): bool
    {
        return $this->is_mandatory;
    }

    public function hasWithdrawalPeriod(): bool
    {
        return ($this->withdrawal_days ?? 0) > 0;
    }

    public function getScheduledWeek(): int
    {
        return (int) ceil($this->scheduled_day / 7);
    }

    public function getAdministrationRouteLabel(): string
    {
        return match ($this->administration_route) {
            'eau' => 'Dans l\'eau de boisson',
            'injection' => 'Par injection',
            'spray' => 'Par nébulisation',
            'oculaire' => 'Goutte oculaire',
            'aliment' => 'Dans l\'aliment',
            default => $this->administration_route ?? '-',
        };
    }

    public function getTreatmentTypeLabel(): string
    {
        return match ($this->treatment_type) {
            'vaccin' => 'Vaccin',
            'vermifuge' => 'Vermifuge',
            'anticoccidien' => 'Anticoccidien',
            'vitamine' => 'Vitamine / Complément',
            'antibiotique' => 'Antibiotique',
            'antiparasitaire' => 'Antiparasitaire',
            'desinfectant' => 'Désinfectant',
            'autre' => 'Autre',
            default => $this->treatment_type,
        };
    }

    /**
     * Retourne les entrées du programme applicables à une bande (flock),
     * ordonnées par jour planifié.
     */
    public static function getProgramForFlock(Flock $flock): \Illuminate\Database\Eloquent\Collection
    {
        $farm = $flock->building?->farm;

        return self::query()
            ->where('organisation_id', $farm?->organisation_id)
            ->forFarm($farm?->id)
            ->forChickenType($flock->chicken_type_id)
            ->orderBy('scheduled_day')
            ->get();
    }

    /**
     * Retourne les traitements à venir pour une bande sur les N prochains jours.
     */
    public static function getUpcomingTreatmentsForFlock(Flock $flock, int $daysAhead = 7): \Illuminate\Database\Eloquent\Collection
    {
        $currentAge = $flock->getCurrentAgeInDays() ?? 0;
        $farm = $flock->building?->farm;

        return self::query()
            ->where('organisation_id', $farm?->organisation_id)
            ->forFarm($farm?->id)
            ->forChickenType($flock->chicken_type_id)
            ->forDayRange($currentAge, $currentAge + $daysAhead)
            ->orderBy('scheduled_day')
            ->get();
    }
}
