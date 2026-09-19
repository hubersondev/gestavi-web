<?php

namespace App\Filament\Farm\Resources\ProductionPhases\Pages;

use App\Filament\Farm\Resources\ProductionPhases\ProductionPhaseResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProductionPhase extends EditRecord
{
    protected static string $resource = ProductionPhaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
