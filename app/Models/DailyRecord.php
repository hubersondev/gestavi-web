<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'flock_id',
        'recorded_by',
        'record_date',
        'age_days',
        'mortality',
        'mortality_cause',
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
    ];

    protected function casts(): array
    {
        return [
            'record_date' => 'date',
            'feed_consumed_kg' => 'decimal:2',
            'water_consumed_l' => 'decimal:2',
            'average_weight_g' => 'decimal:2',
            'temperature_min' => 'decimal:1',
            'temperature_max' => 'decimal:1',
            'humidity_percent' => 'decimal:1',
        ];
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
     * Taux de ponte en pourcentage (œufs collectés / effectif actuel de la bande).
     */
    public function getLayingRate(): ?float
    {
        $quantity = $this->flock?->quantity_current;
        if (! $quantity || $quantity <= 0) {
            return null;
        }

        return round(($this->eggs_collected / $quantity) * 100, 2);
    }

    /**
     * Consommation d'aliment par sujet en grammes.
     */
    public function getFeedPerBird(): ?float
    {
        $quantity = $this->flock?->quantity_current;
        if (! $quantity || $quantity <= 0) {
            return null;
        }

        return round(($this->feed_consumed_kg * 1000) / $quantity, 1);
    }

    /**
     * Consommation d'eau par sujet en millilitres.
     */
    public function getWaterPerBird(): ?float
    {
        $quantity = $this->flock?->quantity_current;
        if (! $quantity || $quantity <= 0) {
            return null;
        }

        return round(($this->water_consumed_l * 1000) / $quantity, 1);
    }

    /**
     * Taux de mortalité journalier en pourcentage.
     */
    public function getMortalityRate(): ?float
    {
        $quantity = $this->flock?->quantity_started;
        if (! $quantity || $quantity <= 0) {
            return null;
        }

        return round(($this->mortality / $quantity) * 100, 4);
    }

    /**
     * Taux de casse en pourcentage.
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
