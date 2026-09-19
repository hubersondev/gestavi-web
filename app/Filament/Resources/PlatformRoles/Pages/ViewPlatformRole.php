<?php

namespace App\Filament\Resources\PlatformRoles\Pages;

use App\Filament\Resources\PlatformRoles\PlatformRoleResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPlatformRole extends ViewRecord
{
    protected static string $resource = PlatformRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
