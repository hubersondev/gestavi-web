<?php

namespace App\Http\Middleware;

use App\Models\Organisation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API : une organisation suspendue répond 403 à tout, une organisation en lecture seule
 * répond 423 Locked aux écritures (POST, PUT, PATCH, DELETE) et sert normalement les lectures.
 * Le mobile garde alors ses saisies en attente au lieu de les marquer refusées.
 */
class EnsureOrganisationWritable
{
    public function handle(Request $request, Closure $next): Response
    {
        $organisation = $request->route('organisation');

        if (! $organisation instanceof Organisation) {
            return $next($request);
        }

        if (! $organisation->status->allowsAccess()) {
            return response()->json([
                'message' => 'Cette organisation est suspendue : contactez le support GESTAVI.',
                'status' => 'suspended',
            ], Response::HTTP_FORBIDDEN);
        }

        if (! $organisation->status->allowsWrites() && ! $request->isMethodSafe()) {
            return response()->json([
                'message' => 'Cette organisation est en lecture seule : les saisies sont conservées sur l\'appareil et seront envoyées quand l\'écriture sera rétablie.',
                'status' => 'read_only',
                'reason' => $organisation->status_reason,
            ], Response::HTTP_LOCKED);
        }

        return $next($request);
    }
}
