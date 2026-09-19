<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index de performance sur les colonnes fréquemment filtrées.
 * Note : PostgreSQL ne crée pas automatiquement d'index sur les colonnes FK
 * (contrairement à MySQL), d'où l'ajout explicite ici.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Flocks — filtrage par statut et par bâtiment+statut (liste et tableaux de bord)
        Schema::table('flocks', function (Blueprint $table) {
            $table->index('status', 'flocks_status_index');
            $table->index(['building_id', 'status'], 'flocks_building_status_index');
        });

        // Organisation users — filtrage par rôle (liste des membres, vérifications d'accès)
        Schema::table('organisation_users', function (Blueprint $table) {
            $table->index('organisation_role_id', 'organisation_users_role_index');
        });

        // Equipment maintenances — alertes planifiées et recherches par date
        Schema::table('equipment_maintenances', function (Blueprint $table) {
            $table->index('next_maintenance_date', 'equipment_maintenances_next_date_index');
            $table->index('scheduled_date', 'equipment_maintenances_scheduled_date_index');
            $table->index('status', 'equipment_maintenances_status_index');
        });

        // Equipment movements — historique par équipement et par date
        Schema::table('equipment_movements', function (Blueprint $table) {
            $table->index('movement_date', 'equipment_movements_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('flocks', function (Blueprint $table) {
            $table->dropIndex('flocks_status_index');
            $table->dropIndex('flocks_building_status_index');
        });

        Schema::table('organisation_users', function (Blueprint $table) {
            $table->dropIndex('organisation_users_role_index');
        });

        Schema::table('equipment_maintenances', function (Blueprint $table) {
            $table->dropIndex('equipment_maintenances_next_date_index');
            $table->dropIndex('equipment_maintenances_scheduled_date_index');
            $table->dropIndex('equipment_maintenances_status_index');
        });

        Schema::table('equipment_movements', function (Blueprint $table) {
            $table->dropIndex('equipment_movements_date_index');
        });
    }
};
