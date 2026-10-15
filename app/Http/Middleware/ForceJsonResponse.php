<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * L'API répond toujours en JSON, même à un navigateur qui demande du HTML :
 * sans ce middleware, une requête non authentifiée tente une redirection vers
 * une route « login » que seuls les panels Filament définissent.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
