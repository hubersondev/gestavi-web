<?php

namespace App\Services;

use App\Models\OrganisationUser;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Destinataires des alertes d'une organisation : ses membres actifs à accès global
 * (propriétaires, administrateurs). Les préférences de chacun filtrent ensuite les canaux.
 */
class AlertRecipients
{
    /**
     * @return Collection<int, User>
     */
    public static function globalAccess(int $organisationId): Collection
    {
        return OrganisationUser::query()
            ->where('organisation_id', $organisationId)
            ->where('status', 'active')
            ->with(['user', 'organisationRole'])
            ->get()
            ->filter(fn (OrganisationUser $membership): bool => $membership->canAccessAllFarms())
            ->map(fn (OrganisationUser $membership): ?User => $membership->user)
            ->filter()
            ->unique('id')
            ->values();
    }
}
