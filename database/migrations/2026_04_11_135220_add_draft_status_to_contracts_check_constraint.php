<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute le statut "draft" (brouillon) à la contrainte check de la table contracts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE contracts DROP CONSTRAINT IF EXISTS contracts_status_check');
        DB::statement("ALTER TABLE contracts ADD CONSTRAINT contracts_status_check CHECK (status IN ('draft','active','expired','renewed','cancelled'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE contracts DROP CONSTRAINT IF EXISTS contracts_status_check');
        DB::statement("ALTER TABLE contracts ADD CONSTRAINT contracts_status_check CHECK (status IN ('active','expired','renewed','cancelled'))");
    }
};
