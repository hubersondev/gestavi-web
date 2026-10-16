<?php

namespace App\Http\Middleware;

use App\Filament\Farm\Pages\OrganisationSuspended;
use App\Models\Organisation;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware tenant Farm : une organisation suspendue n'a plus accès qu'à la page qui explique
 * la suspension. La lecture seule, elle, laisse naviguer : les écritures sont refusées par le
 * garde-fou Eloquent et les actions sont masquées.
 */
class EnsureOrganisationStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Organisation|null $tenant */
        $tenant = Filament::getTenant();

        if (! $tenant || $tenant->status->allowsAccess()) {
            return $next($request);
        }

        $component = $request->route()?->getAction('controller');

        if ($component === OrganisationSuspended::class) {
            return $next($request);
        }

        if ($request->hasHeader('X-Livewire') || $request->expectsJson()) {
            abort(Response::HTTP_FORBIDDEN, 'Cette organisation est suspendue.');
        }

        return redirect()->to(OrganisationSuspended::getUrl(panel: 'farm', tenant: $tenant));
    }
}
