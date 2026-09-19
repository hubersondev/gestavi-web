<?php

namespace App\Filament\Farm\Resources\Veterinarians\Pages;

use App\Filament\Farm\Resources\Veterinarians\VeterinarianResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditVeterinarian extends EditRecord
{
    protected static string $resource = VeterinarianResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
