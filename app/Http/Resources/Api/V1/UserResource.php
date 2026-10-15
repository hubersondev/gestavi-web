<?php

namespace App\Http\Resources\Api\V1;

use App\Models\OrganisationUser;
use App\Models\User;
use App\Services\CurrentFarm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Utilisateur connecté et ses appartenances : organisations, rôle, permissions et fermes accessibles.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentFarm = app(CurrentFarm::class);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'ui_theme' => $this->ui_theme?->value,
            'organisations' => $this->organisations()
                ->wherePivot('status', 'active')
                ->get()
                ->map(function ($organisation) use ($currentFarm): array {
                    /** @var OrganisationUser $membership */
                    $membership = $organisation->pivot;
                    $membership->setRelation('organisation', $organisation);
                    $farmIds = $currentFarm->accessibleFarmIds($membership);

                    return [
                        'id' => $organisation->id,
                        'slug' => $organisation->slug,
                        'name' => $organisation->name,
                        'role' => $membership->organisationRole?->slug,
                        'role_label' => $membership->organisationRole?->name,
                        'all_farms' => $membership->canAccessAllFarms(),
                        'farm_ids' => $farmIds ?? $currentFarm->getAccessibleFarms($membership)->pluck('id')->all(),
                        'permissions' => $membership->organisationRole?->permissions()->pluck('slug')->all() ?? [],
                    ];
                })
                ->values(),
        ];
    }
}
