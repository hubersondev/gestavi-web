<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            // Rendre building_id nullable (un équipement en stock peut ne pas être dans un bâtiment)
            $table->foreignId('building_id')->nullable()->change();

            // Nouvelle FK fournisseur
            $table->foreignId('supplier_id')->nullable()->after('building_id')->constrained()->nullOnDelete();

            // Remplacement de code par inventory_number
            $table->renameColumn('code', 'inventory_number');

            // Nouveaux champs d'identification
            $table->string('serial_number')->nullable()->after('name');

            // Remplacement de type par category
            $table->renameColumn('type', 'category');

            // Marque et modèle
            $table->string('model')->nullable()->after('brand');

            // Capacité de l'équipement
            $table->unsignedInteger('capacity')->nullable()->after('model');
            $table->string('capacity_unit', 30)->nullable()->after('capacity');

            // Champs financiers
            $table->decimal('purchase_price', 12, 2)->nullable()->after('purchase_date');
            $table->date('warranty_expiry_date')->nullable()->after('purchase_price');
            $table->decimal('current_value', 12, 2)->nullable()->after('warranty_expiry_date');
            $table->unsignedSmallInteger('lifespan_years')->nullable()->after('current_value');
            $table->string('purchase_reference')->nullable()->after('lifespan_years');

            // Statut (remplace is_active)
            $table->string('status', 30)->default('in_service')->after('condition');

            // Maintenance
            $table->date('last_maintenance_date')->nullable()->after('status');
            $table->date('next_maintenance_date')->nullable()->after('last_maintenance_date');
            $table->unsignedSmallInteger('maintenance_interval_days')->nullable()->after('next_maintenance_date');

            // Notes
            $table->text('notes')->nullable()->after('description');

            // Supprimer les colonnes obsolètes
            $table->dropColumn(['quantity', 'is_active']);
        });

        // Renommer l'index unique
        Schema::table('equipments', function (Blueprint $table) {
            $table->unique('inventory_number', 'equipments_inventory_number_unique');
        });

        // Supprimer l'ancien index unique sur code (devenu inventory_number)
        Schema::table('equipments', function (Blueprint $table) {
            $table->dropUnique('equipments_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            $table->dropUnique('equipments_inventory_number_unique');
        });

        Schema::table('equipments', function (Blueprint $table) {
            $table->renameColumn('inventory_number', 'code');
            $table->renameColumn('category', 'type');

            $table->unsignedInteger('quantity')->default(1);
            $table->boolean('is_active')->default(true);

            $table->dropForeign(['supplier_id']);
            $table->dropColumn([
                'supplier_id',
                'serial_number',
                'model',
                'capacity',
                'capacity_unit',
                'purchase_price',
                'warranty_expiry_date',
                'current_value',
                'lifespan_years',
                'purchase_reference',
                'status',
                'last_maintenance_date',
                'next_maintenance_date',
                'maintenance_interval_days',
                'notes',
            ]);

            $table->foreignId('building_id')->constrained()->cascadeOnDelete()->change();
        });

        Schema::table('equipments', function (Blueprint $table) {
            $table->unique('code', 'equipments_code_unique');
        });
    }
};
