<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajout de farm_id sur equipments.
 * Un équipement en stock peut ne pas être affecté à un bâtiment (building_id nullable),
 * mais doit rester rattachable à une ferme pour les requêtes de scope organisation.
 * Ajout d'un index composite (farm_id, inventory_number) pour l'unicité par ferme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            $table->foreignId('farm_id')
                ->nullable()
                ->after('building_id')
                ->constrained()
                ->nullOnDelete();
        });

        // Peupler farm_id depuis le bâtiment pour les équipements existants
        // (SQLite ne supporte pas l'alias UPDATE ; on émet une syntaxe portable.)
        DB::statement('
            UPDATE equipments
            SET farm_id = (
                SELECT b.farm_id FROM buildings b WHERE b.id = equipments.building_id
            )
            WHERE building_id IS NOT NULL AND farm_id IS NULL
        ');

        // Unicité de inventory_number par ferme
        Schema::table('equipments', function (Blueprint $table) {
            $table->unique(['farm_id', 'inventory_number'], 'equipments_farm_inventory_unique');
        });
    }

    public function down(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            $table->dropUnique('equipments_farm_inventory_unique');
            $table->dropForeign(['farm_id']);
            $table->dropColumn('farm_id');
        });
    }
};
