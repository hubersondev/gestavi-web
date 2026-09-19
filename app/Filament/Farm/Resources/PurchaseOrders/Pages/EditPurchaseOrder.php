<?php

namespace App\Filament\Farm\Resources\PurchaseOrders\Pages;

use App\Exceptions\CommercialException;
use App\Exceptions\StockException;
use App\Filament\Farm\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Services\StockService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var PurchaseOrder $order */
        $order = $this->record;

        $data['items'] = $order->items()
            ->orderBy('sort_order')
            ->get(['id', 'stock_id', 'product_name', 'description', 'unit', 'quantity_ordered', 'unit_price'])
            ->toArray();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $items = $data['items'] ?? [];
        unset($data['items']);

        try {
            /** @var PurchaseOrder $record */
            $record->update($data);

            return app(StockService::class)->syncPurchaseOrderItems($record, $items);
        } catch (StockException|CommercialException $e) {
            Notification::make()
                ->title('Modification impossible')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }
    }
}
