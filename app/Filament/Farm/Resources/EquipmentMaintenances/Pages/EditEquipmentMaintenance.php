<?php

namespace App\Filament\Farm\Resources\EquipmentMaintenances\Pages;

use App\Filament\Farm\Resources\EquipmentMaintenances\EquipmentMaintenanceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditEquipmentMaintenance extends EditRecord
{
    protected static string $resource = EquipmentMaintenanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
