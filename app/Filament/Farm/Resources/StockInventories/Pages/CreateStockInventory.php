<?php

namespace App\Filament\Farm\Resources\StockInventories\Pages;

use App\Filament\Farm\Resources\StockInventories\StockInventoryResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateStockInventory extends CreateRecord
{
    protected static string $resource = StockInventoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organisation_id'] = Filament::getTenant()?->getKey();
        $data['conducted_by'] ??= auth()->id();

        return $data;
    }
}
