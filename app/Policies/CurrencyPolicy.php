<?php

namespace App\Policies;

use App\Models\Currency;
use App\Models\User;

class CurrencyPolicy
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

    public function view(User $user, Currency $currency): bool
    {
        return $user->hasPermission('settings.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('settings.create');
    }

    public function update(User $user, Currency $currency): bool
    {
        return $user->hasPermission('settings.update');
    }

    public function delete(User $user, Currency $currency): bool
    {
        return $user->hasPermission('settings.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('settings.delete');
    }

    public function restore(User $user, Currency $currency): bool
    {
        return false;
    }

    public function forceDelete(User $user, Currency $currency): bool
    {
        return false;
    }
}
