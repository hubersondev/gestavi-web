<?php

namespace App\Filament\Farm\Resources\EquipmentMovements\Pages;

use App\Filament\Farm\Resources\EquipmentMovements\EquipmentMovementResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditEquipmentMovement extends EditRecord
{
    protected static string $resource = EquipmentMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
