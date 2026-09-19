<?php

namespace App\Filament\Farm\Resources\Stocks\Pages;

use App\Filament\Farm\Resources\Stocks\StockResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateStock extends CreateRecord
{
    protected static string $resource = StockResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organisation_id'] = Filament::getTenant()?->getKey();

        return $data;
    }
}
