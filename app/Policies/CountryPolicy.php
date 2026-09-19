<?php

namespace App\Policies;

use App\Models\Country;
use App\Models\User;

class CountryPolicy
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

    public function view(User $user, Country $country): bool
    {
        return $user->hasPermission('settings.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('settings.create');
    }

    public function update(User $user, Country $country): bool
    {
        return $user->hasPermission('settings.update');
    }

    public function delete(User $user, Country $country): bool
    {
        return $user->hasPermission('settings.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('settings.delete');
    }

    public function restore(User $user, Country $country): bool
    {
        return false;
    }

    public function forceDelete(User $user, Country $country): bool
    {
        return false;
    }
}
