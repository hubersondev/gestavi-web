<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Flock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bande avec ce que l'éleveur doit savoir sur le terrain : effectif, âge, type de suivi, dernier relevé.
 *
 * @mixin Flock
 */
class FlockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $kind = $this->productionKind();

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'status' => $this->status?->value,
            'farm_id' => $this->farm_id,
            'farm' => $this->whenLoaded('farm', fn () => $this->farm?->name),
            'building_id' => $this->building_id,
            'building' => $this->whenLoaded('building', fn () => $this->building?->name),
            'chicken_type' => $this->whenLoaded('chickenType', fn () => $this->chickenType?->name),
            'kind' => $kind->value,
            'tracks_eggs' => $kind->tracksEggs(),
            'tracks_weight' => $kind->tracksWeight(),
            'quantity_started' => (int) $this->quantity_started,
            'quantity_current' => (int) $this->quantity_current,
            'mortality_rate' => round($this->getMortalityRate(), 2),
            'age_days' => $this->getCurrentAgeInDays(),
            'start_date' => $this->start_date?->toDateString(),
            'expected_end_date' => $this->expected_end_date?->toDateString(),
            'last_record_date' => $this->when(isset($this->last_record_date), fn () => $this->last_record_date),
            // Prévisions à J+7 (calculées chaque nuit) : valeur, confiance, référence de la souche
            'forecast' => $this->whenLoaded('predictions', function (): array {
                return $this->predictions
                    ->sortByDesc('target_date')
                    ->unique(fn ($p) => $p->type->value)
                    ->mapWithKeys(fn ($p): array => [$p->type->value => [
                        'value' => (float) $p->predicted_value,
                        'unit' => $p->type->unit(),
                        'confidence' => (float) $p->confidence,
                        'standard' => $p->standard_value !== null ? (float) $p->standard_value : null,
                        'target_date' => $p->target_date->toDateString(),
                    ]])
                    ->all();
            }),
        ];
    }
}
