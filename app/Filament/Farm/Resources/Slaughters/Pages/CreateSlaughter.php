<?php

namespace App\Filament\Farm\Resources\Slaughters\Pages;

use App\Filament\Farm\Resources\Slaughters\SlaughterResource;
use App\Models\Slaughter;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSlaughter extends CreateRecord
{
    protected static string $resource = SlaughterResource::class;

    protected function associateRecordWithTenant(Model $record, Model $tenant): void
    {
        // organisation_id et farm_id sont déduits de la bande par le trait BelongsToOrganisation.
    }

    /**
     * Les garde-fous (effectif présent, délai d'attente) sont portés par le modèle :
     * on traduit leurs exceptions en notification.
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return Slaughter::create($data);
        } catch (\LogicException|\InvalidArgumentException $e) {
            Notification::make()
                ->title('Abattage refusé')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }
    }
}
