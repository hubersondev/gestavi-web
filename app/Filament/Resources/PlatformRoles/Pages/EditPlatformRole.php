<?php

namespace App\Filament\Resources\PlatformRoles\Pages;

use App\Filament\Resources\PlatformRoles\PlatformRoleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPlatformRole extends EditRecord
{
    protected static string $resource = PlatformRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
