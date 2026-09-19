<?php

namespace App\Filament\Farm\Resources\DailyRecords\Pages;

use App\Filament\Farm\Resources\DailyRecords\DailyRecordResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateDailyRecord extends CreateRecord
{
    protected static string $resource = DailyRecordResource::class;

    protected function associateRecordWithTenant(Model $record, Model $tenant): void
    {
        // DailyRecord est lié au tenant indirectement via flock → building → farm → organisation.
        // Le flock_id du formulaire établit déjà cette appartenance ; aucune FK directe nécessaire.
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['recorded_by'] = Filament::auth()->id();

        return $data;
    }
}
