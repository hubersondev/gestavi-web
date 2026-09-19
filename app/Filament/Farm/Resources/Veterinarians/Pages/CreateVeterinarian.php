<?php

namespace App\Filament\Farm\Resources\Veterinarians\Pages;

use App\Filament\Farm\Resources\Veterinarians\VeterinarianResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateVeterinarian extends CreateRecord
{
    protected static string $resource = VeterinarianResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organisation_id'] = Filament::getTenant()?->getKey();

        return $data;
    }
}
