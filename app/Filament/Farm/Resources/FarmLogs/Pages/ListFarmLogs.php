<?php

namespace App\Filament\Farm\Resources\FarmLogs\Pages;

use App\Filament\Farm\Resources\FarmLogs\FarmLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFarmLogs extends ListRecords
{
    protected static string $resource = FarmLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->visible(fn () => FarmLogResource::canCreate()),
        ];
    }
}
