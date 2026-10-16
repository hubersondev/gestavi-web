<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Statut d'organisation à trois valeurs (active, lecture seule, suspendue) avec motif et date.
 * `is_active` est conservé et reste synchronisé (faux = suspendue) pour les requêtes existantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->string('status', 20)->default('active')->after('is_active');
            $table->text('status_reason')->nullable()->after('status');
            $table->timestamp('status_changed_at')->nullable()->after('status_reason');
        });

        DB::table('organisations')->where('is_active', false)->update(['status' => 'suspended']);

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE organisations ADD CONSTRAINT organisations_status_check CHECK (status IN ('active', 'read_only', 'suspended'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE organisations DROP CONSTRAINT IF EXISTS organisations_status_check');
        }

        Schema::table('organisations', function (Blueprint $table): void {
            $table->dropColumn(['status', 'status_reason', 'status_changed_at']);
        });
    }
};
