<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Contraintes CHECK sur les colonnes enum-like des tables équipements.
 * Ces colonnes ont des valeurs prédéfinies mais n'avaient aucune validation BDD.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Statut et état de l'équipement
        DB::statement("
            ALTER TABLE equipments
            ADD CONSTRAINT equipments_status_check
            CHECK (status IN ('in_service', 'under_maintenance', 'out_of_service', 'retired', 'disposed'))
        ");

        DB::statement("
            ALTER TABLE equipments
            ADD CONSTRAINT equipments_condition_check
            CHECK (condition IS NULL OR condition IN ('new', 'good', 'fair', 'poor', 'damaged'))
        ");

        // Statut et type de maintenance
        DB::statement("
            ALTER TABLE equipment_maintenances
            ADD CONSTRAINT equipment_maintenances_status_check
            CHECK (status IN ('scheduled', 'in_progress', 'completed', 'cancelled'))
        ");

        DB::statement("
            ALTER TABLE equipment_maintenances
            ADD CONSTRAINT equipment_maintenances_type_check
            CHECK (maintenance_type IN ('preventive', 'corrective', 'emergency', 'inspection'))
        ");

        // Type de mouvement
        DB::statement("
            ALTER TABLE equipment_movements
            ADD CONSTRAINT equipment_movements_type_check
            CHECK (movement_type IN ('transfer', 'loan', 'return', 'installation', 'decommission'))
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE equipments DROP CONSTRAINT IF EXISTS equipments_status_check');
        DB::statement('ALTER TABLE equipments DROP CONSTRAINT IF EXISTS equipments_condition_check');
        DB::statement('ALTER TABLE equipment_maintenances DROP CONSTRAINT IF EXISTS equipment_maintenances_status_check');
        DB::statement('ALTER TABLE equipment_maintenances DROP CONSTRAINT IF EXISTS equipment_maintenances_type_check');
        DB::statement('ALTER TABLE equipment_movements DROP CONSTRAINT IF EXISTS equipment_movements_type_check');
    }
};
