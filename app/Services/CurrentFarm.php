<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\OrganisationUser;
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

    private function sessionKey(OrganisationUser $membership): string
    {
        return "current_farm_{$membership->organisation_id}_{$membership->user_id}";
    }
}
