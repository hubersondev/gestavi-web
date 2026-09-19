<?php

namespace App\Filament\Farm\Resources\OrganisationRoles\Pages;

use App\Filament\Farm\Resources\OrganisationRoles\OrganisationRoleResource;
use App\Filament\Farm\Resources\OrganisationRoles\Schemas\OrganisationRoleForm;
use App\Models\OrganisationPermission;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOrganisationRole extends EditRecord
{
    protected static string $resource = OrganisationRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->visible(fn (): bool => ! $this->record->is_system),
        ];
    }

    protected function afterSave(): void
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
