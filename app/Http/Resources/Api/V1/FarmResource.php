<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Farm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Farm
 */
class FarmResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'capacity' => $this->capacity,
            'address' => $this->address,
            'phone' => $this->phone,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_active' => (bool) $this->is_active,
            'buildings' => $this->whenLoaded('buildings', fn () => $this->buildings->map(fn ($building): array => [
                'id' => $building->id,
                'code' => $building->code,
                'name' => $building->name,
                'capacity' => $building->capacity,
                'is_active' => (bool) $building->is_active,
            ])->values()),
        ];
    }
}
