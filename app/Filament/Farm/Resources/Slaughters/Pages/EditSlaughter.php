<?php

namespace App\Filament\Farm\Resources\Slaughters\Pages;

use App\Filament\Farm\Resources\Slaughters\SlaughterResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSlaughter extends EditRecord
{
    protected static string $resource = SlaughterResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            $record->update($data);
        } catch (\LogicException|\InvalidArgumentException $e) {
            Notification::make()
                ->title('Modification refusée')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }

        return $record;
    }
}
