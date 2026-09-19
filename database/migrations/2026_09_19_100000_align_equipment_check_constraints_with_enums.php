<?php

use App\Enums\EquipmentCondition;
use App\Enums\EquipmentMovementType;
use App\Enums\MaintenanceType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aligne les contraintes CHECK des tables d'équipements sur les valeurs
 * réellement écrites par l'application (enums PHP + statuts du modèle Equipment).
 *
 * Avant cette migration, « mettre en maintenance », « sortir un équipement »,
 * une maintenance de type nettoyage ou un état « cassé » violaient les CHECK
 * PostgreSQL (valeurs 'in_maintenance', 'exit', 'cleaning', 'broken' absentes).
 */
return new class extends Migration
{
    /**
     * Statuts d'équipement utilisés par le modèle et les formulaires.
     * Les anciennes valeurs ('under_maintenance', 'retired') sont conservées
     * pour ne pas invalider d'éventuelles lignes existantes.
     *
     * @var list<string>
     */
    private const EQUIPMENT_STATUSES = [
        'in_service', 'in_stock', 'in_maintenance', 'out_of_service', 'disposed',
        'under_maintenance', 'retired',
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $this->replaceCheck('equipments', 'equipments_status_check', 'status', self::EQUIPMENT_STATUSES);

        $this->replaceCheck(
            'equipments',
            'equipments_condition_check',
            'condition',
            [...$this->enumValues(EquipmentCondition::class), 'damaged'],
            nullable: true,
        );

        $this->replaceCheck(
            'equipment_maintenances',
            'equipment_maintenances_type_check',
            'maintenance_type',
            [...$this->enumValues(MaintenanceType::class), 'emergency'],
        );

        $this->replaceCheck(
            'equipment_movements',
            'equipment_movements_type_check',
            'movement_type',
            [...$this->enumValues(EquipmentMovementType::class), 'installation', 'decommission'],
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $this->replaceCheck('equipments', 'equipments_status_check', 'status', [
            'in_service', 'under_maintenance', 'out_of_service', 'retired', 'disposed',
        ]);
        $this->replaceCheck('equipments', 'equipments_condition_check', 'condition', [
            'new', 'good', 'fair', 'poor', 'damaged',
        ], nullable: true);
        $this->replaceCheck('equipment_maintenances', 'equipment_maintenances_type_check', 'maintenance_type', [
            'preventive', 'corrective', 'emergency', 'inspection',
        ]);
        $this->replaceCheck('equipment_movements', 'equipment_movements_type_check', 'movement_type', [
            'transfer', 'loan', 'return', 'installation', 'decommission',
        ]);
    }

    /**
     * @param  list<string>  $values
     */
    private function replaceCheck(string $table, string $constraint, string $column, array $values, bool $nullable = false): void
    {
        $list = collect($values)
            ->unique()
            ->map(fn (string $value): string => "'".str_replace("'", "''", $value)."'")
            ->implode(', ');

        $condition = "{$column} IN ({$list})";

        if ($nullable) {
            $condition = "{$column} IS NULL OR {$condition}";
        }

        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint}");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK ({$condition})");
    }

    /**
     * @param  class-string<\BackedEnum>  $enum
     * @return list<string>
     */
    private function enumValues(string $enum): array
    {
        return array_map(fn (\BackedEnum $case): string => (string) $case->value, $enum::cases());
    }
};
