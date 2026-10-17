<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

/**
 * Les annonces relèvent de la relation client : mêmes permissions plateforme que les organisations.
 */
class AnnouncementPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('organisations.view');
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return $user->hasPermission('organisations.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('organisations.update');
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $user->hasPermission('organisations.update');
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $user->hasPermission('organisations.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('organisations.delete');
    }
}
