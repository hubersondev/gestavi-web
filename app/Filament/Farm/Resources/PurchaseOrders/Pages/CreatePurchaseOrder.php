<?php

namespace App\Filament\Farm\Resources\PurchaseOrders\Pages;

use App\Exceptions\StockException;
use App\Filament\Farm\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Services\StockService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $items = $data['items'] ?? [];
        unset($data['items']);

        $data['organisation_id'] = Filament::getTenant()?->getKey();
        $data['created_by'] ??= auth()->id();

        try {
            return app(StockService::class)->createPurchaseOrder($data, $items);
        } catch (StockException $e) {
            Notification::make()
                ->title('Impossible de créer le bon de commande')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }
    }
}
