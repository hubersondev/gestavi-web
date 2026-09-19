<?php

namespace App\Filament\Farm\Resources\EquipmentMaintenances\Pages;

use App\Filament\Farm\Resources\EquipmentMaintenances\EquipmentMaintenanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEquipmentMaintenances extends ListRecords
{
    protected static string $resource = EquipmentMaintenanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
