<?php

namespace App\Filament\Farm\Resources\Equipments\Pages;

use App\Filament\Farm\Resources\Equipments\EquipmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEquipments extends ListRecords
{
    protected static string $resource = EquipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->visible(fn () => EquipmentResource::canCreate()),
        ];
    }
}
