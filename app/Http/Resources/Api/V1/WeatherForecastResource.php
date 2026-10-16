<?php

namespace App\Http\Resources\Api\V1;

use App\Models\WeatherForecast;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Journée de prévision météo d'une ferme.
 *
 * @mixin WeatherForecast
 */
class WeatherForecastResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->forecast_date->toDateString(),
            'temp_min' => $this->temp_min !== null ? (float) $this->temp_min : null,
            'temp_max' => $this->temp_max !== null ? (float) $this->temp_max : null,
            'temp_avg' => $this->temp_avg !== null ? (float) $this->temp_avg : null,
            'humidity' => $this->humidity !== null ? (float) $this->humidity : null,
            'wind_kmh' => $this->windKmh(),
            'rain_mm' => $this->rain_mm !== null ? (float) $this->rain_mm : null,
            'description' => $this->description,
            'icon' => $this->icon,
            'emoji' => $this->emoji(),
        ];
    }
}
