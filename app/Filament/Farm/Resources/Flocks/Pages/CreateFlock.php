<?php

namespace App\Filament\Farm\Resources\Flocks\Pages;

use App\Filament\Farm\Resources\Flocks\FlockResource;
use App\Services\FlockService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateFlock extends CreateRecord
{
    protected static string $resource = FlockResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $flock = app(FlockService::class)->createFlock($data);

        $vaccinationCount = $flock->vaccinations()->count();

        if ($vaccinationCount > 0) {
            Notification::make()
                ->title('Calendrier vaccinal généré')
                ->body("{$vaccinationCount} vaccination(s) programmée(s) depuis le programme sanitaire.")
                ->success()
                ->send();
        }

        return $flock;
    }

    protected function associateRecordWithTenant(Model $record, Model $tenant): void
    {
        // Flock est lié au tenant indirectement via building → farm → organisation.
        // Le building_id du formulaire établit déjà cette appartenance ; aucune FK directe nécessaire.
    }
}
