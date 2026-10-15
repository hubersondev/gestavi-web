<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OrganisationUser;
use App\Services\CurrentFarm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Socle des contrôleurs de l'API v1 : appartenance résolue par le middleware `offline.member`
 * (membre actif de l'organisation de l'URL), permissions d'organisation et cloisonnement par ferme,
 * identiques à ceux du panel.
 */
abstract class ApiController extends Controller
{
    protected function membership(Request $request): OrganisationUser
    {
        $membership = $request->attributes->get('membership');

        if (! $membership instanceof OrganisationUser) {
            throw new HttpException(403, 'Vous n\'êtes pas membre actif de cette organisation.');
        }

        return $membership;
    }

    /**
     * Refuse l'appel (403) si le membre n'a pas la permission d'organisation demandée (ex. sales.create).
     */
    protected function authorizePermission(OrganisationUser $membership, string $permission): void
    {
        if (! $membership->hasPermission($permission)) {
            throw new HttpException(403, "Permission requise : {$permission}.");
        }
    }

    /**
     * Restreint une requête aux fermes accessibles au membre (colonne farm_id qualifiée).
     */
    protected function scopeToFarms(Builder $query, OrganisationUser $membership, string $column): Builder
    {
        return app(CurrentFarm::class)->restrictToFarms($query, $membership, $column);
    }

    /**
     * Refuse l'accès à une ferme qui n'est pas accessible au membre.
     */
    protected function authorizeFarm(OrganisationUser $membership, ?int $farmId): void
    {
        if ($farmId === null) {
            return;
        }

        $ids = app(CurrentFarm::class)->accessibleFarmIds($membership);

        if ($ids !== null && ! in_array($farmId, $ids, true)) {
            throw new HttpException(403, 'Cette ferme ne vous est pas affectée.');
        }
    }

    protected function perPage(Request $request, int $default = 50, int $max = 200): int
    {
        return max(1, min($max, (int) $request->integer('per_page', $default)));
    }
}
