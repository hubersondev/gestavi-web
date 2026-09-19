<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Transforme users.platform_role (string enum) en users.platform_role_id (FK).
     *
     * Étapes :
     * 1. Insérer les rôles et permissions par défaut
     * 2. Ajouter la colonne platform_role_id
     * 3. Mapper les données existantes
     * 4. Supprimer l'ancienne colonne platform_role
     */
    public function up(): void
    {
        // 1. Insérer les 3 rôles système
        $now = now();
        $superAdminId = DB::table('platform_roles')->insertGetId([
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'description' => 'Accès total à la plateforme',
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $adminId = DB::table('platform_roles')->insertGetId([
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'Administration de la plateforme',
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $userId = DB::table('platform_roles')->insertGetId([
            'name' => 'Utilisateur',
            'slug' => 'user',
            'description' => 'Utilisateur standard de la plateforme',
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 2. Insérer les 20 permissions
        $groups = ['users', 'organisations', 'plans', 'roles', 'settings'];
        $actions = ['view', 'create', 'update', 'delete'];
        $actionLabels = [
            'view' => 'Consulter',
            'create' => 'Créer',
            'update' => 'Modifier',
            'delete' => 'Supprimer',
        ];
        $groupLabels = [
            'users' => 'les utilisateurs',
            'organisations' => 'les organisations',
            'plans' => 'les plans',
            'roles' => 'les rôles',
            'settings' => 'les paramètres',
        ];

        $permissionIds = [];
        foreach ($groups as $group) {
            foreach ($actions as $action) {
                $slug = "{$group}.{$action}";
                $permissionIds[$slug] = DB::table('platform_permissions')->insertGetId([
                    'name' => $actionLabels[$action] . ' ' . $groupLabels[$group],
                    'slug' => $slug,
                    'group' => $group,
                    'description' => $actionLabels[$action] . ' ' . $groupLabels[$group],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // 3. Associer les permissions aux rôles
        // Super Admin : toutes les permissions
        foreach ($permissionIds as $permId) {
            DB::table('platform_role_permission')->insert([
                'platform_role_id' => $superAdminId,
                'platform_permission_id' => $permId,
            ]);
        }

        // Admin : toutes sauf roles.create, roles.update, roles.delete
        $adminExcluded = ['roles.create', 'roles.update', 'roles.delete'];
        foreach ($permissionIds as $slug => $permId) {
            if (! in_array($slug, $adminExcluded)) {
                DB::table('platform_role_permission')->insert([
                    'platform_role_id' => $adminId,
                    'platform_permission_id' => $permId,
                ]);
            }
        }

        // Utilisateur : *.view uniquement
        foreach ($groups as $group) {
            $slug = "{$group}.view";
            DB::table('platform_role_permission')->insert([
                'platform_role_id' => $userId,
                'platform_permission_id' => $permissionIds[$slug],
            ]);
        }

        // 4. Ajouter la colonne FK
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('platform_role_id')
                ->nullable()
                ->after('name')
                ->constrained('platform_roles')
                ->nullOnDelete();
        });

        // 5. Mapper les données existantes
        $roleMapping = [
            'super_admin' => $superAdminId,
            'admin' => $adminId,
            'user' => $userId,
        ];

        foreach ($roleMapping as $oldValue => $newId) {
            DB::table('users')
                ->where('platform_role', $oldValue)
                ->update(['platform_role_id' => $newId]);
        }

        // 6. Supprimer l'ancienne colonne et la CHECK constraint
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_platform_role_check');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('platform_role');
        });
    }

    public function down(): void
    {
        // Recréer la colonne platform_role
        Schema::table('users', function (Blueprint $table) {
            $table->string('platform_role', 20)->nullable()->after('name');
        });

        // Remapper les données
        $roles = DB::table('platform_roles')->get();
        $slugToValue = [
            'super-admin' => 'super_admin',
            'admin' => 'admin',
            'user' => 'user',
        ];

        foreach ($roles as $role) {
            if (isset($slugToValue[$role->slug])) {
                DB::table('users')
                    ->where('platform_role_id', $role->id)
                    ->update(['platform_role' => $slugToValue[$role->slug]]);
            }
        }

        // Supprimer la colonne FK
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('platform_role_id');
        });

        // Remettre la CHECK constraint
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_platform_role_check CHECK (platform_role IN ('super_admin', 'admin', 'user'))");
        }
    }
};
