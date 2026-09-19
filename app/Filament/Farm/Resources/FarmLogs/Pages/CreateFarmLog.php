<?php

namespace App\Filament\Farm\Resources\FarmLogs\Pages;

use App\Filament\Farm\Resources\FarmLogs\FarmLogResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateFarmLog extends CreateRecord
{
    protected static string $resource = FarmLogResource::class;

    protected function observeTenancyModelCreation(Model $record, Model $tenant): void
    {
        // farm_id est sélectionné directement dans le formulaire
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['recorded_by'] = Filament::auth()->id();

        return $data;
    }
}
