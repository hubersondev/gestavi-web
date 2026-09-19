<?php

namespace App\Filament\Farm\Concerns;

use Filament\Facades\Filament;

/**
 * Restreint une page (rapport) ou un widget du panel Ferme aux membres
 * détenant une permission d'organisation donnée (ex. 'sales.view').
 *
 * La classe hôte déclare `protected static string $requiredPermission`.
 * Les pages Filament utilisent canAccess(), les widgets canView().
 */
trait RequiresOrganisationPermission
{
    public static function canAccess(): bool
    {
        return static::memberHasRequiredPermission();
    }

    public static function canView(): bool
    {
        return static::memberHasRequiredPermission();
    }

    protected static function memberHasRequiredPermission(): bool
    {
        $permission = property_exists(static::class, 'requiredPermission') ? static::$requiredPermission : '';

        if ($permission === '') {
            return true;
        }

        $user = Filament::auth()->user();
        $tenant = Filament::getTenant();

        if (! $user || ! $tenant) {
            return false;
        }

        return cache()->driver('array')->rememberForever(
            'org_perm|'.$user->getKey().'|'.$tenant->getKey().'|'.$permission,
            fn (): bool => $user->hasOrganisationPermission($tenant, $permission),
        );
    }
}
