<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\OrganisationUser;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Session;

class CurrentFarm
{
    /**
     * Retourne les fermes accessibles par ce membre, ordonnées par nom.
     *
     * @return Collection<int, Farm>
     */
    public function getAccessibleFarms(OrganisationUser $membership): Collection
    {
        return Farm::query()
            ->where('organisation_id', $membership->organisation_id)
            ->where('is_active', true)
            ->accessibleBy($membership)
            ->orderBy('name')
            ->get();
    }

    /**
     * Vérifie si le membre a accès à plusieurs fermes.
     */
    public function hasMultipleFarms(OrganisationUser $membership): bool
    {
        return $this->getAccessibleFarms($membership)->count() > 1;
    }

    /**
     * Retourne la ferme actuellement sélectionnée, ou null (= toutes les fermes).
     * Si le membre n'a qu'une seule ferme, la retourne automatiquement.
     */
    public function get(OrganisationUser $membership): ?Farm
    {
        $farms = $this->getAccessibleFarms($membership);

        if ($farms->count() === 1) {
            return $farms->first();
        }

        $farmId = Session::get($this->sessionKey($membership));

        if ($farmId) {
            return $farms->firstWhere('id', $farmId);
        }

        return null;
    }

    /**
     * Définit la ferme sélectionnée pour ce membre dans la session.
     * Passer null pour revenir à "toutes les fermes".
     */
    public function set(OrganisationUser $membership, ?int $farmId): void
    {
        if ($farmId) {
            Session::put($this->sessionKey($membership), $farmId);
        } else {
            Session::forget($this->sessionKey($membership));
        }
    }

    /**
     * IDs des fermes sur lesquelles les requêtes du membre doivent être restreintes.
     *
     * - `null` : aucune restriction (rôle à accès global sans ferme sélectionnée) ;
     * - `[id]` : une ferme est sélectionnée dans le sélecteur global ;
     * - sinon : les fermes explicitement affectées au membre.
     *
     * @return list<int>|null
     */
    public function accessibleFarmIds(OrganisationUser $membership): ?array
    {
        $currentFarm = $this->get($membership);

        if ($currentFarm) {
            return [(int) $currentFarm->id];
        }

        if ($membership->canAccessAllFarms()) {
            return null;
        }

        return $this->getAccessibleFarms($membership)->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * Restreint une requête aux fermes accessibles via une colonne `farm_id`.
     * Les enregistrements de niveau organisation (farm_id NULL) ne sont visibles
     * que des rôles à accès global (owner/admin).
     */
    public function restrictToFarms(Builder $query, OrganisationUser $membership, string $column = 'farm_id'): Builder
    {
        $ids = $this->accessibleFarmIds($membership);

        if ($ids === null) {
            return $query;
        }

        $column = $this->qualify($query, $column);

        return $query->where(function (Builder $q) use ($ids, $column, $membership): void {
            $q->whereIn($column, $ids);

            if ($membership->canAccessAllFarms()) {
                $q->orWhereNull($column);
            }
        });
    }

    /**
     * Restreint une requête via une relation qui porte la colonne `farm_id`
     * (ex. Transaction → account, Payroll → employee, StockLot → stock).
     */
    public function restrictThroughRelation(Builder $query, OrganisationUser $membership, string $relation, string $column = 'farm_id'): Builder
    {
        if ($this->accessibleFarmIds($membership) === null) {
            return $query;
        }

        return $query->whereHas(
            $relation,
            fn (Builder $q): Builder => $this->restrictToFarms($q, $membership, $column),
        );
    }

    /**
     * Restreint une sous-requête portant directement sur la table `farms`
     * (utilisée dans `whereHas('building.farm', …)` ou `whereHas('flock.building.farm', …)`).
     */
    public function restrictFarmsTable(Builder $farmQuery, OrganisationUser $membership): Builder
    {
        $ids = $this->accessibleFarmIds($membership);

        if ($ids === null) {
            return $farmQuery;
        }

        return $farmQuery->whereIn('farms.id', $ids);
    }

    /**
     * Membership de l'utilisateur connecté dans le tenant Filament courant.
     */
    public function currentMembership(): ?OrganisationUser
    {
        $tenant = Filament::getTenant();
        $user = Filament::auth()->user();

        if (! $tenant || ! $user) {
            return null;
        }

        return $user->membershipIn($tenant);
    }

    /**
     * Variante pour les options de formulaires : restreint la requête aux fermes
     * accessibles à l'utilisateur connecté. `$allowOrganisationLevel` force la
     * visibilité des enregistrements sans ferme (référentiels partagés, ex. fournisseurs réseau).
     */
    public function restrictForCurrentUser(Builder $query, string $column = 'farm_id', bool $allowOrganisationLevel = false): Builder
    {
        $membership = $this->currentMembership();

        if (! $membership) {
            return $query->whereRaw('1 = 0');
        }

        $ids = $this->accessibleFarmIds($membership);

        if ($ids === null) {
            return $query;
        }

        $column = $this->qualify($query, $column);

        return $query->where(function (Builder $q) use ($ids, $column, $membership, $allowOrganisationLevel): void {
            $q->whereIn($column, $ids);

            if ($allowOrganisationLevel || $membership->canAccessAllFarms()) {
                $q->orWhereNull($column);
            }
        });
    }

    /**
     * Variante formulaires pour une sous-requête sur la table `farms`.
     */
    public function restrictFarmsTableForCurrentUser(Builder $farmQuery): Builder
    {
        $membership = $this->currentMembership();

        if (! $membership) {
            return $farmQuery->whereRaw('1 = 0');
        }

        return $this->restrictFarmsTable($farmQuery, $membership);
    }

    private function sessionKey(OrganisationUser $membership): string
    {
        return "current_farm_{$membership->organisation_id}_{$membership->user_id}";
    }

    /**
     * Qualifie la colonne par la table du modèle quand elle ne l'est pas déjà : les requêtes
     * qui joignent `farms` (rapports, comparaisons) porteraient sinon une colonne ambiguë.
     */
    private function qualify(Builder $query, string $column): string
    {
        return str_contains($column, '.') ? $column : $query->qualifyColumn($column);
    }
}
