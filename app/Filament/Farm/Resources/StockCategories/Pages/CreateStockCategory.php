<?php

namespace App\Filament\Farm\Resources\StockCategories\Pages;

use App\Filament\Farm\Resources\StockCategories\StockCategoryResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateStockCategory extends CreateRecord
{
    protected static string $resource = StockCategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organisation_id'] = Filament::getTenant()?->getKey();

        return $data;
    }
}
