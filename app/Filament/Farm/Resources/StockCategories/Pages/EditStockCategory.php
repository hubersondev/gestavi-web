<?php

namespace App\Filament\Farm\Resources\StockCategories\Pages;

use App\Filament\Farm\Resources\StockCategories\StockCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditStockCategory extends EditRecord
{
    protected static string $resource = StockCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
