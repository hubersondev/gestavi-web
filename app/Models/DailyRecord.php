<?php

namespace App\Models;

use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyRecord extends Model
{
    use HasFactory, LogsAuditTrail;

    protected $fillable = [
        'flock_id',
        'recorded_by',
        'record_date',
        'age_days',
        'mortality',
        'mortality_cause',
        'culls',
        'birds_present',
        'eggs_collected',
        'eggs_broken',
        'eggs_sold',
        'feed_consumed_kg',
        'water_consumed_l',
        'average_weight_g',
        'birds_weighed',
        'health_observations',
        'treatments',
        'temperature_min',
        'temperature_max',
        'humidity_percent',
        'notes',
        'correction_reason',
    ];

    /**
     * Fenêtre (en jours) pendant laquelle un relevé reste corrigeable par son saisisseur ;
     * au-delà, seul un membre habilité à supprimer les relevés (admin) peut corriger.
     */
    public const CORRECTION_WINDOW_DAYS = 7;

    public function isWithinCorrectionWindow(): bool
    {
        return $this->record_date !== null
            && $this->record_date->greaterThanOrEqualTo(now()->subDays(self::CORRECTION_WINDOW_DAYS)->startOfDay());
    }

    protected function casts(): array
    {
        return [
            'record_date' => 'date',
            'mortality' => 'integer',
            'culls' => 'integer',
            'birds_present' => 'integer',
            'feed_consumed_kg' => 'decimal:2',
            'water_consumed_l' => 'decimal:2',
            'average_weight_g' => 'decimal:2',
            'temperature_min' => 'decimal:1',
            'temperature_max' => 'decimal:1',
            'humidity_percent' => 'decimal:1',
        ];
    }

    /**
     * L'effectif courant de la bande est DÉRIVÉ des relevés (mortalité, réformes)
     * et des ventes de sujets vivants : toute création, correction rétroactive ou
     * suppression d'un relevé déclenche un recalcul complet, quel que soit le
     * point d'entrée (formulaire, service, import).
     */
    protected static function booted(): void
    {
        static::creating(function (DailyRecord $record): void {
            $record->assertLossesFit((int) $record->mortality + (int) $record->culls);
        });

        static::updating(function (DailyRecord $record): void {
            if ($record->isDirty(['mortality', 'culls'])) {
                $delta = ((int) $record->mortality + (int) $record->culls)
                    - ((int) $record->getOriginal('mortality') + (int) $record->getOriginal('culls'));

                $record->assertLossesFit($delta);
            }
        });

        static::saved(function (DailyRecord $record): void {
            $record->flock?->recalculateQuantities();
            $record->refreshBirdsPresentFrom($record->record_date);
        });

        static::deleted(function (DailyRecord $record): void {
            $record->flock?->recalculateQuantities();
            $record->refreshBirdsPresentFrom($record->record_date);
        });
    }

    /**
     * Refuse des pertes (mortalité + réformes) supérieures à l'effectif présent :
     * l'effectif ne peut jamais devenir négatif.
     */
    protected function assertLossesFit(int $additionalLosses): void
    {
        if ($additionalLosses <= 0) {
            return;
        }

        $flock = Flock::query()->find($this->flock_id);

        if ($flock && $additionalLosses > (int) $flock->quantity_current) {
            throw new \InvalidArgumentException(sprintf(
                'Les pertes saisies (%d) dépassent l\'effectif présent de la bande %s (%d).',
                $additionalLosses,
                $flock->code,
                (int) $flock->quantity_current,
            ));
        }
    }

    /**
     * Recalcule l'effectif présent (fin de journée) de ce relevé et de tous les
     * relevés postérieurs de la bande : une correction rétroactive se propage.
     */
    protected function refreshBirdsPresentFrom(mixed $fromDate): void
    {
        $flock = Flock::query()->find($this->flock_id);

        if (! $flock) {
            return;
        }

        $records = static::query()
            ->where('flock_id', $flock->id)
            ->whereDate('record_date', '>=', $fromDate)
            ->orderBy('record_date')
            ->get(['id', 'record_date', 'mortality', 'culls']);

        foreach ($records as $record) {
            static::withoutEvents(fn () => static::query()
                ->whereKey($record->id)
                ->update(['birds_present' => $flock->birdsPresentOn($record->record_date)]));
        }
    }

    // ===== RELATIONS =====

    public function flock(): BelongsTo
    {
        return $this->belongsTo(Flock::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // ===== SCOPES =====

    public function scopeForDate(Builder $query, mixed $date): Builder
    {
        return $query->whereDate('record_date', $date);
    }

    public function scopeForDateRange(Builder $query, mixed $startDate, mixed $endDate): Builder
    {
        return $query->whereBetween('record_date', [$startDate, $endDate]);
    }

    public function scopeWithMortality(Builder $query): Builder
    {
        return $query->where('mortality', '>', 0);
    }

    public function scopeWithProduction(Builder $query): Builder
    {
        return $query->where('eggs_collected', '>', 0);
    }

    // ===== HELPERS =====

    /**
     * Effectif présent en fin de journée (dénormalisé), à défaut l'effectif courant de la bande.
     */
    public function getBirdsPresent(): int
    {
        return (int) ($this->birds_present ?? $this->flock?->quantity_current ?? 0);
    }

    /**
     * Effectif présent en début de journée (avant les pertes du jour).
     */
    public function getBirdsAtStartOfDay(): int
    {
        return $this->getBirdsPresent() + (int) $this->mortality + (int) $this->culls;
    }

    /**
     * Taux de ponte du jour en % : œufs collectés / poules présentes.
     */
    public function getLayingRate(): ?float
    {
        $birds = $this->getBirdsPresent();

        if ($birds <= 0) {
            return null;
        }

        return round(($this->eggs_collected / $birds) * 100, 2);
    }

    /**
     * Consommation d'aliment par sujet présent, en grammes.
     */
    public function getFeedPerBird(): ?float
    {
        $birds = $this->getBirdsPresent();

        if ($birds <= 0) {
            return null;
        }

        return round(((float) $this->feed_consumed_kg * 1000) / $birds, 1);
    }

    /**
     * Consommation d'eau par sujet présent, en millilitres.
     */
    public function getWaterPerBird(): ?float
    {
        $birds = $this->getBirdsPresent();

        if ($birds <= 0) {
            return null;
        }

        return round(((float) $this->water_consumed_l * 1000) / $birds, 1);
    }

    /**
     * Taux de mortalité journalier en % : morts du jour / effectif présent en début de journée
     * (convention avicole ; l'effectif initial n'est utilisé que pour le cumul).
     */
    public function getMortalityRate(): ?float
    {
        $birds = $this->getBirdsAtStartOfDay();

        if ($birds <= 0) {
            return null;
        }

        return round(($this->mortality / $birds) * 100, 4);
    }

    /**
     * Taux de casse en %.
     */
    public function getEggsBrokenRate(): ?float
    {
        $total = $this->eggs_collected + $this->eggs_broken;
        if ($total <= 0) {
            return null;
        }

        return round(($this->eggs_broken / $total) * 100, 2);
    }

    /**
     * Température moyenne calculée.
     */
    public function getAverageTemperature(): ?float
    {
        if ($this->temperature_min === null || $this->temperature_max === null) {
            return null;
        }

        return round(((float) $this->temperature_min + (float) $this->temperature_max) / 2, 1);
    }

    public function hasHealthIssues(): bool
    {
        return ! empty($this->health_observations) || ! empty($this->treatments);
    }

    public function hasMortality(): bool
    {
        return $this->mortality > 0;
    }

    public function hasProduction(): bool
    {
        return $this->eggs_collected > 0;
    }

    public function hasWeightData(): bool
    {
        return $this->average_weight_g !== null;
    }
}
