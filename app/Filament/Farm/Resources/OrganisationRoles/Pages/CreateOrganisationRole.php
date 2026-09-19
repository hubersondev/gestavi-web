<?php

namespace App\Filament\Farm\Resources\OrganisationRoles\Pages;

use App\Filament\Farm\Resources\OrganisationRoles\OrganisationRoleResource;
use App\Filament\Farm\Resources\OrganisationRoles\Schemas\OrganisationRoleForm;
use App\Models\OrganisationPermission;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateOrganisationRole extends CreateRecord
{
    protected static string $resource = OrganisationRoleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organisation_id'] = Filament::getTenant()->id;

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->syncGroupPermissions();
    }

    private function syncGroupPermissions(): void
    {
        $slugs = [];

        foreach (OrganisationRoleForm::PERMISSION_GROUPS as $groups) {
            foreach (array_keys($groups) as $group) {
                $actions = $this->data["perms_{$group}"] ?? [];
                foreach ($actions as $action) {
                    $slugs[] = "{$group}.{$action}";
                }
            }
        }

        $permIds = OrganisationPermission::whereIn('slug', $slugs)->pluck('id');
        $this->record->permissions()->sync($permIds);
    }
}
