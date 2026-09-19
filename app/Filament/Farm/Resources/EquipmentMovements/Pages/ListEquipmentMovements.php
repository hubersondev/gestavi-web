<?php

namespace App\Filament\Farm\Resources\EquipmentMovements\Pages;

use App\Filament\Farm\Resources\EquipmentMovements\EquipmentMovementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEquipmentMovements extends ListRecords
{
    protected static string $resource = EquipmentMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
