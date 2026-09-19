<?php

namespace App\Filament\Farm\Resources\OrganisationRoles\Pages;

use App\Filament\Farm\Resources\OrganisationRoles\OrganisationRoleResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOrganisationRole extends ViewRecord
{
    protected static string $resource = OrganisationRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn () => OrganisationRoleResource::canEdit($this->record)),
        ];
    }
}
