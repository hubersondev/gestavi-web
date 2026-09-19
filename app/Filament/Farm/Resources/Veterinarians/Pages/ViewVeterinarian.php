<?php

namespace App\Filament\Farm\Resources\Veterinarians\Pages;

use App\Filament\Farm\Resources\Veterinarians\VeterinarianResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVeterinarian extends ViewRecord
{
    protected static string $resource = VeterinarianResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn () => VeterinarianResource::canEdit($this->record)),
            DeleteAction::make()
                ->visible(fn () => VeterinarianResource::canDelete($this->record)),
        ];
    }
}
