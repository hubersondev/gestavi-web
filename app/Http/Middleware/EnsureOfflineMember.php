<?php

namespace App\Http\Middleware;

use App\Models\Organisation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La page « Saisie terrain » et son API ne sont accessibles qu'à un utilisateur
 * connecté, membre actif de l'organisation de l'URL. Le membership est partagé
 * avec le contrôleur via les attributs de la requête.
 */
class EnsureOfflineMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $organisation = $request->route('organisation');
        $user = $request->user();

        // Le binding implicite ne s'applique qu'aux actions typées : on résout le slug si besoin
        if (is_string($organisation)) {
            $organisation = Organisation::query()->where('slug', $organisation)->first();
            $request->route()->setParameter('organisation', $organisation);
        }

        if (! $organisation instanceof Organisation || ! $user) {
            abort(403, 'Accès réservé aux membres de l\'organisation.');
        }

        $membership = $user->membershipIn($organisation);

        if (! $membership || ! $membership->isActive() || ! $user->canAccessTenant($organisation)) {
            abort(403, 'Vous n\'êtes pas membre actif de cette organisation.');
        }

        $request->attributes->set('membership', $membership);

        return $next($request);
    }
}
