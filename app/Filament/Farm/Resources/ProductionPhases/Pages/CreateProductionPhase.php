<?php

namespace App\Filament\Farm\Resources\ProductionPhases\Pages;

use App\Filament\Farm\Resources\ProductionPhases\ProductionPhaseResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateProductionPhase extends CreateRecord
{
    protected static string $resource = ProductionPhaseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organisation_id'] = Filament::getTenant()?->getKey();

        return $data;
    }
}
