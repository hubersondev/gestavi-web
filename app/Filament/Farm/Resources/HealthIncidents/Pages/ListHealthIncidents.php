<?php

namespace App\Filament\Farm\Resources\HealthIncidents\Pages;

use App\Filament\Farm\Resources\HealthIncidents\HealthIncidentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHealthIncidents extends ListRecords
{
    protected static string $resource = HealthIncidentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->visible(fn () => HealthIncidentResource::canCreate()),
        ];
    }
}
