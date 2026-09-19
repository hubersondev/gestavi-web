<?php

namespace App\Filament\Farm\Resources\VetVisits\Pages;

use App\Filament\Farm\Resources\VetVisits\VetVisitResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateVetVisit extends CreateRecord
{
    protected static string $resource = VetVisitResource::class;

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
