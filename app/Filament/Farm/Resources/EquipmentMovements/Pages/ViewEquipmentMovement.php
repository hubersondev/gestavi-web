<?php

namespace App\Filament\Farm\Resources\EquipmentMovements\Pages;

use App\Filament\Farm\Resources\EquipmentMovements\EquipmentMovementResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEquipmentMovement extends ViewRecord
{
    protected static string $resource = EquipmentMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
