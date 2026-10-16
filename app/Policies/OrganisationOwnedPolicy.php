<?php

namespace App\Policies;

use App\Models\Organisation;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy de base pour tout modèle possédant une colonne `organisation_id`.
 * Vérifie l'appartenance organisationnelle : l'utilisateur doit être
 * membre actif de l'organisation propriétaire du record.
 *
 * Les super-admins plateforme bypass toutes les vérifications.
 */
abstract class OrganisationOwnedPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Model $record): bool
    {
        return $this->belongsToUserOrganisation($user, $record);
    }

    public function create(User $user): bool
    {
        return $this->tenantAllowsWrites();
    }

    public function update(User $user, Model $record): bool
    {
        return $this->tenantAllowsWrites() && $this->belongsToUserOrganisation($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->tenantAllowsWrites() && $this->belongsToUserOrganisation($user, $record);
    }

    public function restore(User $user, Model $record): bool
    {
        return $this->tenantAllowsWrites() && $this->belongsToUserOrganisation($user, $record);
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return $this->tenantAllowsWrites() && $this->belongsToUserOrganisation($user, $record);
    }

    /**
     * Dans l'espace d'une organisation en lecture seule ou suspendue, aucune écriture.
     */
    protected function tenantAllowsWrites(): bool
    {
        $tenant = Filament::getTenant();

        return ! ($tenant instanceof Organisation) || $tenant->allowsWrites();
    }

    protected function belongsToUserOrganisation(User $user, Model $record): bool
    {
        if (! property_exists($record, 'organisation_id') && ! isset($record->organisation_id)) {
            return true;
        }

        $organisationId = $record->organisation_id;

        if ($organisationId === null) {
            return true;
        }

        return $user->organisations()
            ->where('organisation_id', $organisationId)
            ->wherePivot('status', 'active')
            ->exists();
    }
}
