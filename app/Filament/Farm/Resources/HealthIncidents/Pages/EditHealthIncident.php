<?php

namespace App\Filament\Farm\Resources\HealthIncidents\Pages;

use App\Filament\Farm\Resources\HealthIncidents\HealthIncidentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditHealthIncident extends EditRecord
{
    protected static string $resource = HealthIncidentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
