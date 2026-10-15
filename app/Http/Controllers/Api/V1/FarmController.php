<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\FarmResource;
use App\Models\Organisation;
use App\Services\CurrentFarm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Fermes accessibles au membre, avec leurs bâtiments.
 */
class FarmController extends ApiController
{
    public function index(Request $request, Organisation $organisation): AnonymousResourceCollection
    {
        $membership = $this->membership($request);

        $farms = app(CurrentFarm::class)
            ->getAccessibleFarms($membership)
            ->load(['buildings' => fn ($query) => $query->where('is_active', true)->orderBy('name')]);

        return FarmResource::collection($farms);
    }
}
