<?php

namespace App\Filament\Farm\Resources\OrganisationRoles\Pages;

use App\Filament\Farm\Resources\OrganisationRoles\OrganisationRoleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrganisationRoles extends ListRecords
{
    protected static string $resource = OrganisationRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Ajouter un rôle')
                ->visible(fn () => OrganisationRoleResource::canCreate()),
        ];
    }
}
