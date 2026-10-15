<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\VaccinationStatus;
use App\Http\Resources\Api\V1\VaccinationResource;
use App\Models\Organisation;
use App\Models\Vaccination;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/**
 * Vaccinations et traitements à faire sur les bandes accessibles au membre (en attente ou
 * reportés), les plus urgents d'abord. La validation passe par `POST /sync` (type vaccination_done).
 */
class VaccinationController extends ApiController
{
    public function index(Request $request, Organisation $organisation): AnonymousResourceCollection
    {
        $membership = $this->membership($request);
        $this->authorizePermission($membership, 'vaccinations.view');

        $query = Vaccination::query()
            ->where('vaccinations.organisation_id', $organisation->id)
            ->with('flock:id,code,name')
            ->orderBy('vaccinations.scheduled_date')
            ->orderBy('vaccinations.id');

        $status = $request->string('status', 'todo')->toString();

        if ($status === 'todo') {
            $query->whereIn('vaccinations.status', [VaccinationStatus::Pending, VaccinationStatus::Delayed]);
        } elseif ($status !== 'all') {
            $query->where('vaccinations.status', $status);
        }

        if ($request->filled('flock_id')) {
            $query->where('vaccinations.flock_id', (int) $request->integer('flock_id'));
        }

        if ($request->filled('farm_id')) {
            $this->authorizeFarm($membership, (int) $request->integer('farm_id'));
            $query->where('vaccinations.farm_id', (int) $request->integer('farm_id'));
        }

        if ($request->filled('since')) {
            $query->where('vaccinations.updated_at', '>=', Carbon::parse($request->string('since')->toString()));
        }

        $this->scopeToFarms($query, $membership, 'vaccinations.farm_id');

        return VaccinationResource::collection($query->paginate($this->perPage($request, 200, 500)));
    }
}
