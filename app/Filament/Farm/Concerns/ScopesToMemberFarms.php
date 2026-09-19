<?php

namespace App\Filament\Farm\Concerns;

use App\Models\Farm;
use App\Models\OrganisationUser;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

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

    /**
     * Cloisonne la requête sur les fermes accessibles au membre courant
     * (colonne `farm_id` directe). Sans membership : aucun résultat.
     */
    protected static function applyFarmScope(Builder $query, string $column = 'farm_id'): Builder
    {
        $membership = static::currentMembership();

        if (! $membership) {
            return $query->whereRaw('1 = 0');
        }

        return app(CurrentFarm::class)->restrictToFarms($query, $membership, $column);
    }

    /**
     * Cloisonne la requête via une relation portant `farm_id`
     * (ex. `account`, `employee`, `stock`, `sale`, `equipment`).
     */
    protected static function applyFarmScopeThrough(Builder $query, string $relation, string $column = 'farm_id'): Builder
    {
        $membership = static::currentMembership();

        if (! $membership) {
            return $query->whereRaw('1 = 0');
        }

        return app(CurrentFarm::class)->restrictThroughRelation($query, $membership, $relation, $column);
    }

    /**
     * Cloisonne une sous-requête sur la table `farms` (chaînes building.farm, flock.building.farm).
     */
    protected static function applyFarmsTableScope(Builder $farmQuery): Builder
    {
        $membership = static::currentMembership();

        if (! $membership) {
            return $farmQuery->whereRaw('1 = 0');
        }

        return app(CurrentFarm::class)->restrictFarmsTable($farmQuery, $membership);
    }
}
