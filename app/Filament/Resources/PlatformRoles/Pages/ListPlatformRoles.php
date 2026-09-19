<?php

namespace App\Filament\Resources\PlatformRoles\Pages;

use App\Filament\Resources\PlatformRoles\PlatformRoleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlatformRoles extends ListRecords
{
    protected static string $resource = PlatformRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
