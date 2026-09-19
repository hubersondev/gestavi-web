<?php

namespace App\Filament\Farm\Resources\Sales\Pages;

use App\Exceptions\CommercialException;
use App\Filament\Farm\Resources\Sales\SaleResource;
use App\Services\SaleService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSale extends CreateRecord
{
    protected static string $resource = SaleResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $items = $data['items'] ?? [];
        unset($data['items']);

        $data['organisation_id'] = Filament::getTenant()?->getKey();
        $data['user_id'] ??= auth()->id();

        try {
            return app(SaleService::class)->createSale($data, $items);
        } catch (CommercialException $e) {
            Notification::make()
                ->title('Impossible de créer la facture')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }
    }
}
