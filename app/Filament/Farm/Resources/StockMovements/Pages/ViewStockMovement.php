<?php

namespace App\Filament\Farm\Resources\StockMovements\Pages;

use App\Filament\Farm\Resources\StockMovements\StockMovementResource;
use Filament\Resources\Pages\ViewRecord;

class ViewStockMovement extends ViewRecord
{
    protected static string $resource = StockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
