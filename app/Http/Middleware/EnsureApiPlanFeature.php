<?php

namespace App\Http\Middleware;

use App\Enums\PlanFeature;
use App\Models\Organisation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware API : bloque l'accès si la feature n'est pas
 * incluse dans le plan de l'organisation.
 *
 * Usage : Route::middleware('plan.feature:buildings')
 */
class EnsureApiPlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $organisation = $this->resolveOrganisation($request);

        if (! $organisation) {
            return response()->json([
                'message' => 'Organisation introuvable.',
            ], Response::HTTP_FORBIDDEN);
        }

        $planFeature = PlanFeature::tryFrom($feature);
        $featureToCheck = $planFeature ?? $feature;

        if (! $organisation->hasFeature($featureToCheck)) {
            return response()->json([
                'message' => "Cette fonctionnalité n'est pas incluse dans votre plan.",
                'feature' => $feature,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    /**
     * Résoudre l'organisation depuis la requête.
     */
    private function resolveOrganisation(Request $request): ?Organisation
    {
        // Depuis un paramètre de route
        $organisation = $request->route('organisation');

        if ($organisation instanceof Organisation) {
            return $organisation;
        }

        // Depuis l'utilisateur authentifié (première organisation active)
        $user = $request->user();

        if ($user) {
            return $user->organisations()
                ->wherePivot('status', 'active')
                ->first();
        }

        return null;
    }
}
