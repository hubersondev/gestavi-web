<?php

namespace App\Services;

use App\Models\OrganisationUser;

/**
 * Cache d'appartenance par requête : une seule lecture de `organisation_users` (avec le rôle et
 * ses permissions) par couple utilisateur / organisation, quel que soit le nombre d'instances
 * de User ou d'appels (cloisonnement, permissions, colonnes, policies, API).
 *
 * Singleton du conteneur : vidé à chaque requête HTTP et à chaque test ; invalidé quand une
 * appartenance est enregistrée ou supprimée.
 */
class MembershipResolver
{
    /** @var array<string, OrganisationUser|null> */
    private array $memberships = [];

    public function resolve(int $userId, int $organisationId): ?OrganisationUser
    {
        $key = $userId.'|'.$organisationId;

        if (! array_key_exists($key, $this->memberships)) {
            $this->memberships[$key] = OrganisationUser::query()
                ->where('user_id', $userId)
                ->where('organisation_id', $organisationId)
                ->with('organisationRole.permissions')
                ->first();
        }

        return $this->memberships[$key];
    }

    /** Oublie les appartenances d'un utilisateur (ou toutes). */
    public function forget(?int $userId = null): void
    {
        if ($userId === null) {
            $this->memberships = [];

            return;
        }

        foreach (array_keys($this->memberships) as $key) {
            if (str_starts_with($key, $userId.'|')) {
                unset($this->memberships[$key]);
            }
        }
    }

    public function flush(): void
    {
        $this->memberships = [];
    }
}
