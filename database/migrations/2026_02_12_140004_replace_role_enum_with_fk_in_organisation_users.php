<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rôles système à créer pour chaque organisation existante.
     *
     * @var array<string, array{name: string, weight: int}>
     */
    private array $systemRoles = [
        'owner' => ['name' => 'Propriétaire', 'weight' => 4],
        'admin' => ['name' => 'Administrateur', 'weight' => 3],
        'manager' => ['name' => 'Gérant', 'weight' => 2],
        'employee' => ['name' => 'Employé', 'weight' => 1],
    ];

    public function up(): void
    {
        // 1. Ajouter la colonne FK (nullable pour l'instant)
        Schema::table('organisation_users', function (Blueprint $table) {
            $table->foreignId('organisation_role_id')
                ->nullable()
                ->after('user_id')
                ->constrained('organisation_roles')
                ->nullOnDelete();
        });

        // 2. Pour chaque organisation existante, créer les rôles système et mapper les données
        $organisations = DB::table('organisations')->get();

        foreach ($organisations as $organisation) {
            $roleIdMap = [];

            foreach ($this->systemRoles as $slug => $data) {
                $roleId = DB::table('organisation_roles')->insertGetId([
                    'organisation_id' => $organisation->id,
                    'name' => $data['name'],
                    'slug' => $slug,
                    'weight' => $data['weight'],
                    'is_system' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $roleIdMap[$slug] = $roleId;
            }

            // Mapper les enregistrements existants
            foreach ($roleIdMap as $slug => $roleId) {
                DB::table('organisation_users')
                    ->where('organisation_id', $organisation->id)
                    ->where('role', $slug)
                    ->update(['organisation_role_id' => $roleId]);
            }
        }

        // 3. Supprimer le CHECK constraint et la colonne role
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE organisation_users DROP CONSTRAINT IF EXISTS organisation_users_role_check');
        }

        Schema::table('organisation_users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('organisation_users', function (Blueprint $table) {
            $table->string('role', 20)->default('employee')->after('user_id');
        });

        // Re-mapper les rôles FK vers les valeurs enum
        $members = DB::table('organisation_users')
            ->whereNotNull('organisation_role_id')
            ->get();

        foreach ($members as $member) {
            $role = DB::table('organisation_roles')
                ->where('id', $member->organisation_role_id)
                ->first();

            if ($role) {
                DB::table('organisation_users')
                    ->where('id', $member->id)
                    ->update(['role' => $role->slug]);
            }
        }

        Schema::table('organisation_users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organisation_role_id');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE organisation_users ADD CONSTRAINT organisation_users_role_check CHECK (role IN ('owner', 'admin', 'manager', 'employee'))");
        }
    }
};
