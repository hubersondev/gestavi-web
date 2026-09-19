<?php

namespace App\Filament\Farm\Resources\Quotes\Pages;

use App\Exceptions\CommercialException;
use App\Filament\Farm\Resources\Quotes\QuoteResource;
use App\Models\Quote;
use App\Services\SaleService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditQuote extends EditRecord
{
    protected static string $resource = QuoteResource::class;

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
        /** @var Quote $quote */
        $quote = $this->record;

        $data['items'] = $quote->items()
            ->orderBy('sort_order')
            ->get(['id', 'product_name', 'description', 'unit', 'quantity', 'unit_price', 'discount_percent', 'flock_id'])
            ->toArray();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $items = $data['items'] ?? [];
        unset($data['items']);

        try {
            /** @var Quote $record */
            $record->update($data);

            return app(SaleService::class)->syncQuoteItems($record, $items);
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
