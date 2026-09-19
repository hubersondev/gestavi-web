<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Un fournisseur doit être rattaché à au moins une organisation ou une ferme.
 * Les deux ne peuvent pas être NULL simultanément.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('
                ALTER TABLE suppliers
                ADD CONSTRAINT suppliers_organisation_or_farm_check
                CHECK (farm_id IS NOT NULL OR organisation_id IS NOT NULL)
            ');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE suppliers DROP CONSTRAINT IF EXISTS suppliers_organisation_or_farm_check');
        }
    }
};
