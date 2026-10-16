<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;

/**
 * Les abonnements relèvent de la gestion des organisations : mêmes permissions plateforme.
 */
class SubscriptionPolicy
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
        return $user->hasPermission('organisations.view');
    }

    public function view(User $user, Subscription $subscription): bool
    {
        return $user->hasPermission('organisations.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('organisations.update');
    }

    public function update(User $user, Subscription $subscription): bool
    {
        return $user->hasPermission('organisations.update');
    }

    public function delete(User $user, Subscription $subscription): bool
    {
        return $user->hasPermission('organisations.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('organisations.delete');
    }
}
