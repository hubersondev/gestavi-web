<?php

namespace App\Filament\Farm\Resources\StockInventories\Pages;

use App\Filament\Farm\Resources\StockInventories\StockInventoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStockInventories extends ListRecords
{
    protected static string $resource = StockInventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
