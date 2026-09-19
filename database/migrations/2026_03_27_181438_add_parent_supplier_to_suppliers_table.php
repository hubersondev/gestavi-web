<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // Rattachement à l'organisation pour les fournisseurs réseau (sans ferme)
            $table->foreignId('organisation_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();

            // farm_id devient nullable : les fournisseurs réseau n'appartiennent pas à une ferme spécifique
            $table->foreignId('farm_id')
                ->nullable()
                ->change();

            // Auto-référencement pour regrouper les branches d'un même réseau fournisseur
            $table->foreignId('parent_supplier_id')
                ->nullable()
                ->after('farm_id')
                ->constrained('suppliers')
                ->nullOnDelete();
        });

        // Peupler organisation_id depuis la ferme pour les fournisseurs existants
        // (SQLite ne supporte pas l'alias UPDATE ; on émet une syntaxe portable.)
        DB::statement('
            UPDATE suppliers
            SET organisation_id = (
                SELECT f.organisation_id FROM farms f WHERE f.id = suppliers.farm_id
            )
            WHERE farm_id IS NOT NULL AND organisation_id IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropForeign(['parent_supplier_id']);
            $table->dropColumn('parent_supplier_id');

            $table->dropForeign(['organisation_id']);
            $table->dropColumn('organisation_id');

            $table->foreignId('farm_id')->nullable(false)->change();
        });
    }
};
