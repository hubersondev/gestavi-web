<?php

namespace App\Filament\Farm\Resources\EquipmentMovements\Pages;

use App\Filament\Farm\Resources\EquipmentMovements\EquipmentMovementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEquipmentMovement extends CreateRecord
{
    protected static string $resource = EquipmentMovementResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['moved_by'] ??= auth()->id();

        return $data;
    }
}
