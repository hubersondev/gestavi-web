<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Vaccination;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vaccination ou traitement planifié sur une bande, tel que le mobile l'affiche et le valide.
 *
 * @mixin Vaccination
 */
class VaccinationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'flock_id' => $this->flock_id,
            'flock_code' => $this->whenLoaded('flock', fn () => $this->flock?->code),
            'flock_name' => $this->whenLoaded('flock', fn () => $this->flock?->name),
            'farm_id' => $this->farm_id,
            'treatment_name' => $this->treatment_name,
            'treatment_type' => $this->treatment_type,
            'administration_route' => $this->administration_route,
            'dosage' => $this->dosage,
            'scheduled_date' => $this->scheduled_date?->toDateString(),
            'administered_date' => $this->administered_date?->toDateString(),
            'age_at_treatment' => $this->age_at_treatment,
            'status' => $this->status?->value,
            'is_overdue' => $this->isOverdue(),
            'withdrawal_days' => (int) ($this->withdrawal_days ?? 0),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
