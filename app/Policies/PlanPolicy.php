<?php

namespace App\Policies;

use App\Models\Plan;
use App\Models\User;

class PlanPolicy
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
        return $user->hasPermission('plans.view');
    }

    public function view(User $user, Plan $plan): bool
    {
        return $user->hasPermission('plans.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('plans.create');
    }

    public function update(User $user, Plan $plan): bool
    {
        return $user->hasPermission('plans.update');
    }

    public function delete(User $user, Plan $plan): bool
    {
        return $user->hasPermission('plans.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('plans.delete');
    }

    public function restore(User $user, Plan $plan): bool
    {
        return $user->hasPermission('plans.update');
    }

    public function forceDelete(User $user, Plan $plan): bool
    {
        return false;
    }
}
