<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionPhase extends Model
{
    use HasFactory;

    protected $fillable = [
        'organisation_id',
        'chicken_type_id',
        'name',
        'code',
        'start_day',
        'end_day',
        'min_temperature',
        'max_temperature',
        'humidity_min',
        'humidity_max',
        'light_hours',
        'density_per_m2',
        'feed_type',
        'description',
        'order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_temperature' => 'decimal:1',
            'max_temperature' => 'decimal:1',
            'humidity_min' => 'decimal:1',
            'humidity_max' => 'decimal:1',
            'density_per_m2' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    // ===== RELATIONS =====

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function chickenType(): BelongsTo
    {
        return $this->belongsTo(ChickenType::class);
    }

    // ===== SCOPES =====

    public function scopeForChickenType($query, int $chickenTypeId): mixed
    {
        return $query->where('chicken_type_id', $chickenTypeId);
    }

    public function scopeActive($query): mixed
    {
        return $query->where('is_active', true);
    }

    public function scopeForAge($query, int $ageDays): mixed
    {
        return $query->where('start_day', '<=', $ageDays)
            ->where('end_day', '>=', $ageDays);
    }

    // ===== HELPERS =====

    public function getDuration(): int
    {
        return $this->end_day - $this->start_day + 1;
    }

    public function getDurationInWeeks(): float
    {
        return round($this->getDuration() / 7, 1);
    }

    public function getOptimalTemperature(): ?float
    {
        if ($this->min_temperature === null || $this->max_temperature === null) {
            return null;
        }

        return ($this->min_temperature + $this->max_temperature) / 2;
    }

    public function getOptimalHumidity(): ?float
    {
        if ($this->humidity_min === null || $this->humidity_max === null) {
            return null;
        }

        return ($this->humidity_min + $this->humidity_max) / 2;
    }

    public function isTemperatureInRange(float $temperature): bool
    {
        return $temperature >= $this->min_temperature && $temperature <= $this->max_temperature;
    }

    public function isHumidityInRange(float $humidity): bool
    {
        return $humidity >= $this->humidity_min && $humidity <= $this->humidity_max;
    }

    public function containsAge(int $ageDays): bool
    {
        return $ageDays >= $this->start_day && $ageDays <= $this->end_day;
    }

    public function getProgressForAge(int $ageDays): ?float
    {
        if (! $this->containsAge($ageDays)) {
            return null;
        }

        $daysInPhase = $ageDays - $this->start_day;

        return ($daysInPhase / $this->getDuration()) * 100;
    }

    /**
     * Phase applicable à un âge donné, pour l'organisation indiquée uniquement
     * (chaque organisation possède son propre paramétrage de phases).
     */
    public static function getPhaseForAge(int $chickenTypeId, int $ageDays, ?int $organisationId = null): ?self
    {
        return self::where('chicken_type_id', $chickenTypeId)
            ->when(
                $organisationId !== null,
                fn ($query) => $query->where('organisation_id', $organisationId),
                fn ($query) => $query->whereNull('organisation_id'),
            )
            ->where('start_day', '<=', $ageDays)
            ->where('end_day', '>=', $ageDays)
            ->where('is_active', true)
            ->first();
    }
}
