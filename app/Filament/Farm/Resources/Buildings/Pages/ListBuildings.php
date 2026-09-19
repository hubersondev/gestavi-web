<?php

namespace App\Filament\Farm\Resources\Buildings\Pages;

use App\Filament\Farm\Resources\Buildings\BuildingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBuildings extends ListRecords
{
    protected static string $resource = BuildingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->visible(fn () => BuildingResource::canCreate()),
        ];
    }
}
