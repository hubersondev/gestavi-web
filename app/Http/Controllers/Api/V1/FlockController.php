<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\FlockResource;
use App\Models\Flock;
use App\Models\Organisation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Bandes de l'organisation, cloisonnées aux fermes du membre. Par défaut les bandes actives.
 */
class FlockController extends ApiController
{
    public function index(Request $request, Organisation $organisation): AnonymousResourceCollection
    {
        $membership = $this->membership($request);
        $status = $request->string('status', 'active')->toString();

        $query = Flock::query()
            ->where('flocks.organisation_id', $organisation->id)
            ->with(['farm:id,name', 'building:id,name', 'chickenType:id,name,production_kind'])
            ->withMax('dailyRecords as last_record_date', 'record_date')
            ->orderBy('flocks.code');

        if ($status !== 'all') {
            $query->where('flocks.status', $status);
        }

        if ($request->filled('farm_id')) {
            $this->authorizeFarm($membership, (int) $request->integer('farm_id'));
            $query->where('flocks.farm_id', (int) $request->integer('farm_id'));
        }

        $this->scopeToFarms($query, $membership, 'flocks.farm_id');

        return FlockResource::collection($query->paginate($this->perPage($request, 100)));
    }

    public function show(Request $request, Organisation $organisation, Flock $flock): FlockResource
    {
        $membership = $this->membership($request);

        abort_if($flock->organisation_id !== $organisation->id, 404);
        $this->authorizeFarm($membership, $flock->farm_id);

        $flock->load(['farm:id,name', 'building:id,name', 'chickenType:id,name,production_kind']);
        $flock->setAttribute('last_record_date', $flock->dailyRecords()->max('record_date'));

        return new FlockResource($flock);
    }
}
