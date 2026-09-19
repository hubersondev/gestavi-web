<?php

namespace App\Filament\Farm\Concerns;

use App\Models\Farm;
use App\Models\OrganisationUser;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;

trait ScopesToMemberFarms
{
    /**
     * Récupère le membership de l'utilisateur courant dans le tenant actif.
     */
    protected static function currentMembership(): ?OrganisationUser
    {
        $tenant = Filament::getTenant();
        $user = Filament::auth()->user();

        if (! $tenant || ! $user) {
            return null;
        }

        return $user->membershipIn($tenant);
    }

    /**
     * Retourne la ferme sélectionnée dans le header, ou null (= toutes les fermes).
     */
    protected static function currentFarm(): ?Farm
    {
        $membership = static::currentMembership();

        if (! $membership) {
            return null;
        }

        return app(CurrentFarm::class)->get($membership);
    }
}
