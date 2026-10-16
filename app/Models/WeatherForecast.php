<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prévision météo d'une journée pour une ferme, agrégée depuis les tranches de 3 heures du
 * fournisseur : températures, humidité, vent, pluie, nébulosité, pression, description.
 */
class WeatherForecast extends Model
{
    public const SOURCE_OPENWEATHERMAP = 'openweathermap';

    protected $fillable = [
        'organisation_id', 'farm_id', 'forecast_date', 'source',
        'temp_min', 'temp_max', 'temp_avg', 'humidity',
        'wind_speed', 'wind_gust', 'rain_mm', 'clouds_percent', 'pressure_hpa',
        'description', 'icon', 'raw_data', 'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'forecast_date' => 'date',
            'temp_min' => 'decimal:2',
            'temp_max' => 'decimal:2',
            'temp_avg' => 'decimal:2',
            'humidity' => 'decimal:2',
            'wind_speed' => 'decimal:2',
            'wind_gust' => 'decimal:2',
            'rain_mm' => 'decimal:2',
            'clouds_percent' => 'decimal:2',
            'pressure_hpa' => 'decimal:2',
            'raw_data' => 'array',
            'fetched_at' => 'datetime',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function scopeForFarm(Builder $query, int $farmId): Builder
    {
        return $query->where('farm_id', $farmId);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereDate('forecast_date', '>=', now()->toDateString());
    }

    /** Vitesse du vent en km/h. */
    public function windKmh(): ?float
    {
        return $this->wind_speed === null ? null : round((float) $this->wind_speed * 3.6, 1);
    }

    /** « 24–33 °C ». */
    public function temperatureRange(): string
    {
        if ($this->temp_min === null && $this->temp_max === null) {
            return '—';
        }

        return round((float) $this->temp_min).'–'.round((float) $this->temp_max).' °C';
    }

    /** Pictogramme d'après le code d'icône OpenWeatherMap (01d, 10n…). */
    public function emoji(): string
    {
        $code = substr((string) $this->icon, 0, 2);

        return match ($code) {
            '01' => '☀️',
            '02' => '🌤️',
            '03' => '⛅',
            '04' => '☁️',
            '09' => '🌧️',
            '10' => '🌦️',
            '11' => '⛈️',
            '13' => '🌨️',
            '50' => '🌫️',
            default => '🌤️',
        };
    }
}
