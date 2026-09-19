<?php

namespace App\Filament\Farm\Resources\HealthIncidents\Pages;

use App\Filament\Farm\Resources\HealthIncidents\HealthIncidentResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateHealthIncident extends CreateRecord
{
    protected static string $resource = HealthIncidentResource::class;

    protected function associateRecordWithTenant(Model $record, Model $tenant): void
    {
        // HealthIncident est lié au tenant indirectement via flock → building → farm → organisation.
        // Le flock_id du formulaire établit déjà cette appartenance ; aucune FK directe nécessaire.
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['reported_by'] = Filament::auth()->id();

        return $data;
    }
}
