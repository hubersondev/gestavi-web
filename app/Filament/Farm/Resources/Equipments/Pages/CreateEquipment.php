<?php

namespace App\Filament\Farm\Resources\Equipments\Pages;

use App\Filament\Farm\Resources\Equipments\EquipmentResource;
use App\Models\Building;
use App\Models\Equipment;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateEquipment extends CreateRecord
{
    protected static string $resource = EquipmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = Filament::getTenant();

        // Générer le numéro d'inventaire
        if (empty($data['inventory_number'])) {
            $data['inventory_number'] = Equipment::generateInventoryNumber($tenant->getKey());
        }

        // Peupler farm_id depuis le bâtiment sélectionné
        if (! empty($data['building_id']) && empty($data['farm_id'])) {
            $building = Building::find($data['building_id']);
            $data['farm_id'] = $building?->farm_id;
        }

        // Calculer la valeur actuelle par amortissement
        if (! empty($data['purchase_price']) && ! empty($data['lifespan_years'])) {
            $equipment = new Equipment($data);
            $data['current_value'] = $equipment->calculateCurrentValue();
        }

        return $data;
    }
}
