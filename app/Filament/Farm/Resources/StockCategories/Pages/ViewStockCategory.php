<?php

namespace App\Filament\Farm\Resources\StockCategories\Pages;

use App\Filament\Farm\Resources\StockCategories\StockCategoryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewStockCategory extends ViewRecord
{
    protected static string $resource = StockCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
