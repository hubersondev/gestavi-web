<?php

namespace App\Http\Resources\Api\V1;

use App\Models\DailyRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DailyRecord
 */
class DailyRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'flock_id' => $this->flock_id,
            'record_date' => $this->record_date?->toDateString(),
            'age_days' => $this->age_days,
            'mortality' => (int) $this->mortality,
            'mortality_cause' => $this->mortality_cause,
            'culls' => (int) $this->culls,
            'birds_present' => $this->birds_present,
            'eggs_collected' => (int) $this->eggs_collected,
            'eggs_broken' => (int) $this->eggs_broken,
            'eggs_sold' => (int) $this->eggs_sold,
            'feed_consumed_kg' => (float) $this->feed_consumed_kg,
            'water_consumed_l' => (float) $this->water_consumed_l,
            'average_weight_g' => $this->average_weight_g,
            'birds_weighed' => $this->birds_weighed,
            'temperature_min' => $this->temperature_min,
            'temperature_max' => $this->temperature_max,
            'humidity_percent' => $this->humidity_percent,
            'health_observations' => $this->health_observations,
            'treatments' => $this->treatments,
            'notes' => $this->notes,
            'recorded_by' => $this->recorded_by,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
