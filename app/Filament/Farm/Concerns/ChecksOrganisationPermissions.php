<?php

namespace App\Filament\Farm\Concerns;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait à appliquer sur chaque Resource du panel Farm.
 *
 * Définit `$permissionGroup` (ex: 'flocks') et surcharge les méthodes
 * Filament d'autorisation pour vérifier les permissions de l'organisation.
 *
 * Les résultats sont mis en cache en mémoire (array cache) pour éviter les
 * N+1 lors du rendu de la navigation (environ 15 appels par chargement).
 */
trait ChecksOrganisationPermissions
{
    // ──────────────────────────────────────────────
    //  Méthodes d'autorisation Filament
    // ──────────────────────────────────────────────

    public static function canViewAny(): bool
    {
        return static::memberHasPermission('view');
    }

    public static function canCreate(): bool
    {
        return static::memberHasPermission('create');
    }

    public static function canEdit(Model $record): bool
    {
        return static::memberHasPermission('update');
    }

    public static function canView(Model $record): bool
    {
        return static::memberHasPermission('view');
    }

    public static function canDelete(Model $record): bool
    {
        return static::memberHasPermission('delete');
    }

    public static function canDeleteAny(): bool
    {
        return static::memberHasPermission('delete');
    }

    /**
     * Vérifie une permission d'action personnalisée (ex: flocks.start).
     */
    public static function canPerformAction(string $action): bool
    {
        return static::memberHasPermission($action);
    }

    // ──────────────────────────────────────────────
    //  Vérification interne (avec cache par requête)
    // ──────────────────────────────────────────────

    protected static function memberHasPermission(string $action): bool
    {
        $group = property_exists(static::class, 'permissionGroup') ? static::$permissionGroup : '';

        if (empty($group)) {
            return true;
        }

        $user = Filament::auth()->user();
        $tenant = Filament::getTenant();

        if (! $user || ! $tenant) {
            return false;
        }

        $slug = $group . '.' . $action;

        return cache()->driver('array')->rememberForever(
            'org_perm|' . $user->getKey() . '|' . $tenant->getKey() . '|' . $slug,
            fn (): bool => $user->hasOrganisationPermission($tenant, $slug),
        );
    }
}
