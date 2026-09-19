<?php

namespace App\Filament\Farm\Resources\DailyRecords\Pages;

use App\Filament\Farm\Resources\DailyRecords\DailyRecordResource;
use App\Models\Flock;
use App\Services\FlockService;
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

    /**
     * La création passe par FlockService afin que la mortalité saisie
     * décrémente l'effectif courant de la bande (quantity_current).
     */
    protected function handleRecordCreation(array $data): Model
    {
        $flock = Flock::query()->findOrFail($data['flock_id']);

        $data['recorded_by'] = Filament::auth()->id();

        return app(FlockService::class)->recordDailyData($flock, $data);
    }
}
