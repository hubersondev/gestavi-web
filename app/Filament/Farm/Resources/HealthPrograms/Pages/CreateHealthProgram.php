<?php

namespace App\Filament\Farm\Resources\HealthPrograms\Pages;

use App\Filament\Farm\Resources\HealthPrograms\HealthProgramResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateHealthProgram extends CreateRecord
{
    protected static string $resource = HealthProgramResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organisation_id'] = Filament::getTenant()?->getKey();

        return $data;
    }
}
