<?php

namespace App\Filament\Farm\Resources\StockLots\Pages;

use App\Filament\Farm\Resources\StockLots\StockLotResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewStockLot extends ViewRecord
{
    protected static string $resource = StockLotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
