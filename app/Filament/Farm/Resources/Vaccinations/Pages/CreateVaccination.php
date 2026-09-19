<?php

namespace App\Filament\Farm\Resources\Vaccinations\Pages;

use App\Filament\Farm\Resources\Vaccinations\VaccinationResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateVaccination extends CreateRecord
{
    protected static string $resource = VaccinationResource::class;

    protected function associateRecordWithTenant(Model $record, Model $tenant): void
    {
        // Vaccination est lié au tenant indirectement via flock → building → farm → organisation.
        // Le flock_id du formulaire établit déjà cette appartenance ; aucune FK directe nécessaire.
    }
}
