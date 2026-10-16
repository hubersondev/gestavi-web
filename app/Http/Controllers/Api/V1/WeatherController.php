<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\WeatherForecastResource;
use App\Models\Farm;
use App\Models\Organisation;
use App\Services\Weather\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Météo d'une ferme pour le mobile : conditions du jour, prévisions à 5 jours, conditions à risque
 * pour ses bandes. Lecture seule, à partir des prévisions déjà récupérées côté serveur.
 */
class WeatherController extends ApiController
{
    public function show(Request $request, Organisation $organisation, Farm $farm, WeatherService $service): JsonResponse
    {
        $membership = $this->membership($request);
        abort_if($farm->organisation_id !== $organisation->id, 404);
        $this->authorizeFarm($membership, $farm->id);

        $current = $service->current($farm);

        return response()->json([
            'enabled' => WeatherService::enabled(),
            'located' => $farm->latitude !== null && $farm->longitude !== null,
            'current' => $current ? new WeatherForecastResource($current) : null,
            'forecast' => WeatherForecastResource::collection($service->upcoming($farm, 5)),
            'alerts' => collect($service->anomaliesFor($farm))->map(fn (array $a): array => [
                'flock_id' => $a['flock']->id,
                'flock_code' => $a['flock']->code,
                'date' => $a['date']->toDateString(),
                'kind' => $a['kind'],
                'critical' => $a['critical'],
                'message' => $a['message'],
            ])->values(),
            'fetched_at' => $service->lastFetchedAt($farm)?->toIso8601String(),
        ]);
    }
}
