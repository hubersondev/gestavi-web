<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ajouter la colonne type sur users
        Schema::table('users', function (Blueprint $table) {
            $table->string('type', 20)->default('farmer')->after('name');
        });

        // 2. Créer la table pivot platform_role_user
        Schema::create('platform_role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique('user_id');
        });

        // 3. Migrer les données : users avec platform_role_id → type=system + pivot
        $usersWithRole = DB::table('users')->whereNotNull('platform_role_id')->get();

        foreach ($usersWithRole as $user) {
            DB::table('users')
                ->where('id', $user->id)
                ->update(['type' => 'system']);

            DB::table('platform_role_user')->insert([
                'user_id' => $user->id,
                'platform_role_id' => $user->platform_role_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 4. Supprimer la FK et la colonne platform_role_id
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('platform_role_id');
        });

        // 5. CHECK constraint sur type (PostgreSQL uniquement)
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_type_check CHECK (type IN ('system', 'farmer'))");
        }
    }

    public function down(): void
    {
        // Recréer la colonne platform_role_id
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('platform_role_id')
                ->nullable()
                ->after('name')
                ->constrained('platform_roles')
                ->nullOnDelete();
        });

        // Remapper les données depuis le pivot
        $pivotRecords = DB::table('platform_role_user')->get();
        foreach ($pivotRecords as $record) {
            DB::table('users')
                ->where('id', $record->user_id)
                ->update(['platform_role_id' => $record->platform_role_id]);
        }

        // Supprimer le CHECK constraint
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_type_check');
        }

        // Supprimer la table pivot et la colonne type
        Schema::dropIfExists('platform_role_user');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
