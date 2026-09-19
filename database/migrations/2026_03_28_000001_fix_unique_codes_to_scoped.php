<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les codes étaient uniques globalement (toutes organisations confondues).
 * On les rend uniques par périmètre métier (organisation, ferme, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        // farms.code : unique par organisation
        Schema::table('farms', function (Blueprint $table) {
            $table->dropUnique('farms_code_unique');
            $table->unique(['organisation_id', 'code'], 'farms_organisation_code_unique');
        });

        // buildings.code : unique par ferme
        Schema::table('buildings', function (Blueprint $table) {
            $table->dropUnique('buildings_code_unique');
            $table->unique(['farm_id', 'code'], 'buildings_farm_code_unique');
        });

        // flocks.code : suppression de l'unique global
        // L'unicité par organisation est gérée au niveau applicatif (Rule::unique + whereHas)
        Schema::table('flocks', function (Blueprint $table) {
            $table->dropUnique('flocks_code_unique');
        });

        // suppliers.code : unique par organisation (code est nullable, les NULL sont ignorés par l'index)
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropUnique('suppliers_code_unique');
            $table->unique(['organisation_id', 'code'], 'suppliers_organisation_code_unique');
        });

        // equipments.inventory_number : suppression de l'unique global
        // L'unicité sera gérée via farm_id ajouté dans une prochaine migration
        Schema::table('equipments', function (Blueprint $table) {
            $table->dropUnique('equipments_inventory_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('farms', function (Blueprint $table) {
            $table->dropUnique('farms_organisation_code_unique');
            $table->unique('code', 'farms_code_unique');
        });

        Schema::table('buildings', function (Blueprint $table) {
            $table->dropUnique('buildings_farm_code_unique');
            $table->unique('code', 'buildings_code_unique');
        });

        Schema::table('flocks', function (Blueprint $table) {
            $table->unique('code', 'flocks_code_unique');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropUnique('suppliers_organisation_code_unique');
            $table->unique('code', 'suppliers_code_unique');
        });

        Schema::table('equipments', function (Blueprint $table) {
            $table->unique('inventory_number', 'equipments_inventory_number_unique');
        });
    }
};
