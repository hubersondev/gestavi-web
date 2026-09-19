<?php

namespace App\Policies;

use App\Models\ChickenType;
use App\Models\User;

class ChickenTypePolicy
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
        return $user->hasPermission('settings.view');
    }

    public function view(User $user, ChickenType $chickenType): bool
    {
        return $user->hasPermission('settings.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('settings.create');
    }

    public function update(User $user, ChickenType $chickenType): bool
    {
        return $user->hasPermission('settings.update');
    }

    public function delete(User $user, ChickenType $chickenType): bool
    {
        return $user->hasPermission('settings.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('settings.delete');
    }

    public function restore(User $user, ChickenType $chickenType): bool
    {
        return false;
    }

    public function forceDelete(User $user, ChickenType $chickenType): bool
    {
        return false;
    }
}
