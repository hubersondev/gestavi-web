<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Pièce d'identité
            $table->string('id_type', 30)->nullable()->after('cnps_number');
            $table->string('id_number', 100)->nullable()->after('id_type');
            $table->date('id_expiry_date')->nullable()->after('id_number');

            // Photo
            $table->string('photo')->nullable()->after('id_expiry_date');

            // Type d'emploi
            $table->string('employment_type', 20)->nullable()->after('department');

            // Résidence sur site
            $table->boolean('is_resident')->default(false)->after('notes');
            $table->boolean('is_on_site')->default(false)->after('is_resident');
            $table->string('accommodation')->nullable()->after('is_on_site');
            $table->date('residence_start_date')->nullable()->after('accommodation');
            $table->boolean('requires_patrol')->default(false)->after('residence_start_date');
            $table->unsignedSmallInteger('patrol_interval_hours')->nullable()->after('requires_patrol');
        });

        DB::statement("ALTER TABLE employees ADD CONSTRAINT employees_id_type_check CHECK (id_type IN ('cni','passport','attestation','carte_consulaire'))");
        DB::statement("ALTER TABLE employees ADD CONSTRAINT employees_employment_type_check CHECK (employment_type IN ('permanent','temporary','daily','seasonal'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE employees DROP CONSTRAINT IF EXISTS employees_id_type_check');
        DB::statement('ALTER TABLE employees DROP CONSTRAINT IF EXISTS employees_employment_type_check');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['id_type', 'id_number', 'id_expiry_date', 'photo', 'employment_type', 'is_resident', 'is_on_site', 'accommodation', 'residence_start_date', 'requires_patrol', 'patrol_interval_hours']);
        });
    }
};
