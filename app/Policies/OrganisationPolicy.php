<?php

namespace App\Policies;

use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\User;

class OrganisationPolicy
{
    private function membership(User $user, Organisation $organisation): ?OrganisationUser
    {
        return $user->organisations()
            ->where('organisation_id', $organisation->id)
            ->wherePivot('status', 'active')
            ->first()
            ?->pivot;
    }

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

    /**
     * Voir l'organisation → tout membre actif.
     */
    public function view(User $user, Organisation $organisation): bool
    {
        return $organisation->hasMember($user);
    }

    /**
     * Modifier les paramètres → owner ou admin.
     */
    public function update(User $user, Organisation $organisation): bool
    {
        return $this->membership($user, $organisation)?->isAdmin() ?? false;
    }

    /**
     * Supprimer l'organisation → owner uniquement.
     */
    public function delete(User $user, Organisation $organisation): bool
    {
        return $this->membership($user, $organisation)?->isOwner() ?? false;
    }

    /**
     * Inviter un nouveau membre → admin ou plus.
     */
    public function invite(User $user, Organisation $organisation): bool
    {
        return $this->membership($user, $organisation)?->isAdmin() ?? false;
    }

    /**
     * Retirer un membre.
     * - On peut se retirer soi-même (sauf dernier owner).
     * - Sinon, il faut un rôle strictement supérieur à la cible.
     */
    public function removeMember(User $user, Organisation $organisation, User $target): bool
    {
        if ($user->id === $target->id) {
            $membership = $this->membership($user, $organisation);

            if ($membership?->isOwner()) {
                return $organisation->owners()->count() > 1;
            }

            return $membership !== null;
        }

        $actorMembership = $this->membership($user, $organisation);
        $targetMembership = $this->membership($target, $organisation);

        if (! $actorMembership || ! $targetMembership) {
            return false;
        }

        $targetRole = $targetMembership->organisationRole;

        return $targetRole && $actorMembership->isAbove($targetRole);
    }

    /**
     * Changer le rôle d'un membre.
     * - L'acteur doit être au-dessus du rôle actuel ET du nouveau rôle.
     * - On ne peut pas changer son propre rôle.
     */
    public function changeRole(
        User $user,
        Organisation $organisation,
        User $target,
    ): bool {
        if ($user->id === $target->id) {
            return false;
        }

        $actorMembership = $this->membership($user, $organisation);
        $targetMembership = $this->membership($target, $organisation);

        if (! $actorMembership || ! $targetMembership) {
            return false;
        }

        $targetRole = $targetMembership->organisationRole;

        return $targetRole && $actorMembership->isAbove($targetRole);
    }

    /**
     * Suspendre un membre → même logique que removeMember.
     */
    public function suspendMember(User $user, Organisation $organisation, User $target): bool
    {
        return $this->removeMember($user, $organisation, $target);
    }
}
