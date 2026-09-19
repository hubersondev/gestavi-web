<?php

namespace App\Filament\Resources\FarmUsers\Pages;

use App\Enums\MemberStatus;
use App\Enums\UserType;
use App\Filament\Resources\FarmUsers\FarmUserResource;
use App\Models\Organisation;
use App\Models\OrganisationRole;
use Filament\Resources\Pages\CreateRecord;

class CreateFarmUser extends CreateRecord
{
    protected static string $resource = FarmUserResource::class;

    private ?int $organisationId = null;

    private ?int $organisationRoleId = null;

    /**
     * Forcer le type Farmer et extraire les champs d'affectation.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = UserType::Farmer;

        $this->organisationId = $data['organisation_id'] ?? null;
        $this->organisationRoleId = $data['organisation_role_id'] ?? null;

        unset($data['organisation_id'], $data['organisation_role_id']);

        return $data;
    }

    /**
     * Rattacher l'utilisateur à la structure après création.
     */
    protected function afterCreate(): void
    {
        if ($this->organisationId && $this->organisationRoleId) {
            $organisation = Organisation::find($this->organisationId);
            $role = OrganisationRole::find($this->organisationRoleId);

            if ($organisation && $role) {
                $organisation->addMember($this->record, $role, MemberStatus::Active);
            }
        }
    }
}
