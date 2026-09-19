<?php

namespace App\Policies;

use App\Models\PlatformRole;
use App\Models\User;

class PlatformRolePolicy
{
    /**
     * Les super admins plateforme bypass tout.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('roles.view');
    }

    public function view(User $user, PlatformRole $role): bool
    {
        return $user->hasPermission('roles.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('roles.create');
    }

    public function update(User $user, PlatformRole $role): bool
    {
        return $user->hasPermission('roles.update');
    }

    public function delete(User $user, PlatformRole $role): bool
    {
        // Interdit de supprimer un rôle système
        if ($role->is_system) {
            return false;
        }

        return $user->hasPermission('roles.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('roles.delete');
    }

    public function restore(User $user, PlatformRole $role): bool
    {
        return false;
    }

    public function forceDelete(User $user, PlatformRole $role): bool
    {
        return false;
    }
}
