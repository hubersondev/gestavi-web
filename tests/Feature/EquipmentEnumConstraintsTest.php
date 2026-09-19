<?php

use App\Enums\EquipmentCondition;
use App\Enums\EquipmentMovementType;
use App\Enums\MaintenanceType;
use App\Models\Equipment;
use App\Models\EquipmentMaintenance;
use App\Models\EquipmentMovement;

/**
 * Garantit que chaque valeur écrite par l'application est acceptée par les
 * contraintes CHECK PostgreSQL (les tests tournent sur pgsql, cf. phpunit.xml).
 */
it('accepte chaque statut d\'équipement utilisé par le modèle', function (string $status) {
    $equipment = Equipment::factory()->create(['status' => 'in_service']);

    $equipment->update(['status' => $status]);

    expect($equipment->fresh()->status)->toBe($status);
})->with(['in_service', 'in_stock', 'in_maintenance', 'out_of_service', 'disposed']);

it('accepte chaque état d\'équipement de l\'enum EquipmentCondition', function (EquipmentCondition $condition) {
    $equipment = Equipment::factory()->create(['condition' => $condition->value]);

    expect($equipment->fresh()->condition)->toBe($condition->value);
})->with(EquipmentCondition::cases());

it('accepte chaque type de maintenance de l\'enum MaintenanceType', function (MaintenanceType $type) {
    $maintenance = EquipmentMaintenance::factory()->create(['maintenance_type' => $type->value]);

    expect($maintenance->fresh()->maintenance_type)->toBe($type->value);
})->with(MaintenanceType::cases());

it('accepte chaque type de mouvement de l\'enum EquipmentMovementType', function (EquipmentMovementType $type) {
    $movement = EquipmentMovement::factory()->create(['movement_type' => $type->value]);

    expect($movement->fresh()->movement_type)->toBe($type->value);
})->with(EquipmentMovementType::cases());

it('met un équipement en maintenance puis le sort sans violer les contraintes', function () {
    $equipment = Equipment::factory()->inService()->create();

    $equipment->putInMaintenance();
    expect($equipment->fresh()->isInMaintenance())->toBeTrue();

    $movement = $equipment->moveTo(null, 'Sortie pour réparation externe');

    expect($movement->movement_type)->toBe('exit')
        ->and($equipment->fresh()->building_id)->toBeNull();
});
