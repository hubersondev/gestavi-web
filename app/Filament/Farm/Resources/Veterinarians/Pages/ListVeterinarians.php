<?php

namespace App\Filament\Farm\Resources\Veterinarians\Pages;

use App\Filament\Farm\Resources\Veterinarians\VeterinarianResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVeterinarians extends ListRecords
{
    protected static string $resource = VeterinarianResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->visible(fn () => VeterinarianResource::canCreate()),
        ];
    }
}
