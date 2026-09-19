<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Contraintes de cohérence sur la table flocks.
 * quantity_current ne peut pas dépasser quantity_started (taux de mortalité négatif impossible).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('
            ALTER TABLE flocks
            ADD CONSTRAINT flocks_quantity_check
            CHECK (quantity_current <= quantity_started)
        ');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE flocks DROP CONSTRAINT IF EXISTS flocks_quantity_check');
    }
};
