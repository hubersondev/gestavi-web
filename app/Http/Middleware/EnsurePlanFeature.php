<?php

namespace App\Http\Middleware;

use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Pages\UpgradePlan;
use App\Models\Organisation;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware tenant Farm : redirige vers la page Upgrade
 * si la resource/page courante nécessite une fonctionnalité
 * non incluse dans le plan de l'organisation.
 */
class EnsurePlanFeature
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Organisation|null $tenant */
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return $next($request);
        }

        // Résoudre le composant Livewire depuis la route
        $componentClass = $this->resolveComponentClass($request);

        if (! $componentClass) {
            return $next($request);
        }

        // Vérifier si le composant (ou sa resource parente) utilise le trait
        if (! $this->usesRequiresPlanFeatures($componentClass)) {
            return $next($request);
        }

        // Seule l'absence de fonctionnalité dans le plan renvoie vers la page de changement
        // de plan ; un défaut de permission est traité en 403 par la page elle-même
        if ($componentClass::tenantHasRequiredFeatures()) {
            return $next($request);
        }

        // Rediriger vers la page Upgrade
        return redirect()->to(UpgradePlan::getUrl(panel: 'farm', tenant: $tenant));
    }

    /**
     * Résoudre la classe du composant depuis la route Filament.
     */
    private function resolveComponentClass(Request $request): ?string
    {
        $route = $request->route();

        if (! $route) {
            return null;
        }

        // Les routes Filament stockent le composant Livewire
        $component = $route->getAction('controller');

        if ($component && class_exists($component)) {
            // Pour les pages de resource, remonter à la resource parente
            if (method_exists($component, 'getResource')) {
                return $component::getResource();
            }

            return $component;
        }

        return null;
    }

    /**
     * Vérifier si la classe utilise le trait RequiresPlanFeatures.
     */
    private function usesRequiresPlanFeatures(string $class): bool
    {
        return in_array(RequiresPlanFeatures::class, class_uses_recursive($class));
    }
}
