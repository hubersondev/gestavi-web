<?php

namespace App\Filament\Farm\Resources\Sales\Pages;

use App\Exceptions\CommercialException;
use App\Filament\Farm\Resources\Sales\SaleResource;
use App\Models\Sale;
use App\Services\SaleService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSale extends EditRecord
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * Les lignes ne sont plus liées par `relationship()` : on les charge explicitement.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Sale $sale */
        $sale = $this->record;

        $data['items'] = $sale->items()
            ->orderBy('sort_order')
            ->get(['id', 'product_name', 'description', 'unit', 'quantity', 'unit_price', 'discount_percent', 'flock_id', 'is_live_birds'])
            ->toArray();

        return $data;
    }

    /**
     * L'en-tête est sauvegardé par Filament ; les lignes sont resynchronisées par le service
     * (refus si un paiement existe déjà).
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $items = $data['items'] ?? [];
        unset($data['items']);

        try {
            /** @var Sale $record */
            $record->update($data);

            return app(SaleService::class)->syncSaleItems($record, $items);
        } catch (CommercialException $e) {
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
