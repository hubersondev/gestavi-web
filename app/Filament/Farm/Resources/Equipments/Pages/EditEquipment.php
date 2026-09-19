<?php

namespace App\Filament\Farm\Resources\Equipments\Pages;

use App\Filament\Farm\Resources\Equipments\EquipmentResource;
use App\Models\Building;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditEquipment extends EditRecord
{
    protected static string $resource = EquipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Mettre à jour farm_id si le bâtiment change
        if (! empty($data['building_id'])) {
            $building = Building::find($data['building_id']);
            $data['farm_id'] = $building?->farm_id;
        }

        // Recalculer la valeur actuelle
        if (! empty($data['purchase_price']) && ! empty($data['lifespan_years'])) {
            $this->record->fill($data);
            $data['current_value'] = $this->record->calculateCurrentValue();
        }

        return $data;
    }
}
