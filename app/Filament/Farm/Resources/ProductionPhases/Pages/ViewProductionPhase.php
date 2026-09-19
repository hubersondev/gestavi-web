<?php

namespace App\Filament\Farm\Resources\ProductionPhases\Pages;

use App\Filament\Farm\Resources\ProductionPhases\ProductionPhaseResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProductionPhase extends ViewRecord
{
    protected static string $resource = ProductionPhaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn () => ProductionPhaseResource::canEdit($this->record)),
            DeleteAction::make()
                ->visible(fn () => ProductionPhaseResource::canDelete($this->record)),
        ];
    }
}
