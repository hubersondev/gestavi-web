<?php

namespace App\Filament\Farm\Resources\Quotes\Pages;

use App\Exceptions\CommercialException;
use App\Filament\Farm\Resources\Quotes\QuoteResource;
use App\Services\SaleService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateQuote extends CreateRecord
{
    protected static string $resource = QuoteResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $items = $data['items'] ?? [];
        unset($data['items']);

        $data['organisation_id'] = Filament::getTenant()?->getKey();
        $data['user_id'] ??= auth()->id();

        try {
            return app(SaleService::class)->createQuote($data, $items);
        } catch (CommercialException $e) {
            Notification::make()
                ->title('Impossible de créer le devis')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }
    }
}
