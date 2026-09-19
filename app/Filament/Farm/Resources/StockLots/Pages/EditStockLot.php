<?php

namespace App\Filament\Farm\Resources\StockLots\Pages;

use App\Filament\Farm\Resources\StockLots\StockLotResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditStockLot extends EditRecord
{
    protected static string $resource = StockLotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }
}
