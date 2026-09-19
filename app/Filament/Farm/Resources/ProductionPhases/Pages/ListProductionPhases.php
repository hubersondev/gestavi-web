<?php

namespace App\Filament\Farm\Resources\ProductionPhases\Pages;

use App\Filament\Farm\Resources\ProductionPhases\ProductionPhaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductionPhases extends ListRecords
{
    protected static string $resource = ProductionPhaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->visible(fn () => ProductionPhaseResource::canCreate()),
        ];
    }
}
