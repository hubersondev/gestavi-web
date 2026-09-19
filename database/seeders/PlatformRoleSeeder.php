<?php

namespace Database\Seeders;

use App\Models\PlatformPermission;
use App\Models\PlatformRole;
use Illuminate\Database\Seeder;

class PlatformRoleSeeder extends Seeder
{
    /**
     * Crée ou vérifie les rôles et permissions système (idempotent).
     */
    public function run(): void
    {
        // Créer les permissions
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

        $permissions = [];
        foreach ($groups as $group) {
            foreach ($actions as $action) {
                $slug = "{$group}.{$action}";
                $permissions[$slug] = PlatformPermission::firstOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => $actionLabels[$action].' '.$groupLabels[$group],
                        'group' => $group,
                        'description' => $actionLabels[$action].' '.$groupLabels[$group],
                    ],
                );
            }
        }

        // Créer les rôles système
        $superAdmin = PlatformRole::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'description' => 'Accès total à la plateforme', 'is_system' => true],
        );

        $admin = PlatformRole::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'description' => 'Administration de la plateforme', 'is_system' => true],
        );

        $user = PlatformRole::firstOrCreate(
            ['slug' => 'user'],
            ['name' => 'Utilisateur', 'description' => 'Utilisateur standard de la plateforme', 'is_system' => true],
        );

        // Associer les permissions
        // Super Admin : toutes
        $superAdmin->permissions()->syncWithoutDetaching(
            collect($permissions)->pluck('id'),
        );

        // Admin : toutes sauf roles.create/update/delete
        $adminExcluded = ['roles.create', 'roles.update', 'roles.delete'];
        $adminPermIds = collect($permissions)
            ->filter(fn ($perm, $slug) => ! in_array($slug, $adminExcluded))
            ->pluck('id');
        $admin->permissions()->syncWithoutDetaching($adminPermIds);

        // Utilisateur : *.view uniquement
        $userPermIds = collect($permissions)
            ->filter(fn ($perm, $slug) => str_ends_with($slug, '.view'))
            ->pluck('id');
        $user->permissions()->syncWithoutDetaching($userPermIds);
    }
}
