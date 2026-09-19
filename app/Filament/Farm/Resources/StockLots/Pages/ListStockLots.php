<?php

namespace App\Filament\Farm\Resources\StockLots\Pages;

use App\Filament\Farm\Resources\StockLots\StockLotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStockLots extends ListRecords
{
    protected static string $resource = StockLotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
