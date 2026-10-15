<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Offline\SyncProcessor;
use App\Http\Requests\Offline\SyncRequest;
use App\Http\Resources\Api\V1\DailyRecordResource;
use App\Models\DailyRecord;
use App\Models\Organisation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/**
 * Relevés quotidiens : lecture incrémentale (paramètre `since`) et synchronisation hors-ligne
 * idempotente, identique à celle de la page terrain (relevés et vaccinations faites).
 */
class DailyRecordController extends ApiController
{
    public function __construct(private readonly SyncProcessor $processor) {}

    public function index(Request $request, Organisation $organisation): AnonymousResourceCollection
    {
        $membership = $this->membership($request);

        $query = DailyRecord::query()
            ->where('daily_records.organisation_id', $organisation->id)
            ->orderByDesc('daily_records.record_date')
            ->orderByDesc('daily_records.id');

        if ($request->filled('flock_id')) {
            $query->where('daily_records.flock_id', (int) $request->integer('flock_id'));
        }

        if ($request->filled('since')) {
            $query->where('daily_records.updated_at', '>=', Carbon::parse($request->string('since')->toString()));
        }

        $this->scopeToFarms($query, $membership, 'daily_records.farm_id');

        return DailyRecordResource::collection($query->paginate($this->perPage($request, 100, 500)));
    }

    /**
     * Lot de saisies hors-ligne `{"entries": [{client_uuid, type, payload, recorded_at}]}` ;
     * chaque saisie reçoit un verdict created / duplicate / rejected, rejouable sans doublon.
     */
    public function sync(SyncRequest $request, Organisation $organisation): JsonResponse
    {
        $membership = $this->membership($request);

        $results = $this->processor->process($organisation, $request->user(), $membership, $request->validated('entries'));

        return response()->json(['results' => $results, 'synced_at' => now()->toIso8601String()]);
    }
}
