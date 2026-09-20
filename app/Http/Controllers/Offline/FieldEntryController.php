<?php

namespace App\Http\Controllers\Offline;

use App\Enums\VaccinationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Offline\SyncRequest;
use App\Models\Flock;
use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\Vaccination;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

/**
 * Page mobile « Saisie terrain » et son API JSON (bandes, vaccinations, synchronisation).
 */
class FieldEntryController extends Controller
{
    public function __construct(private readonly SyncProcessor $processor) {}

    public function page(Request $request, Organisation $organisation): View
    {
        return view('offline.field-entry', [
            'organisation' => $organisation,
            'user' => $request->user(),
            'urls' => [
                'page' => route('offline.page', $organisation),
                'flocks' => route('offline.flocks', $organisation),
                'vaccinations' => route('offline.vaccinations', $organisation),
                'csrf' => route('offline.csrf', $organisation),
                'sync' => route('offline.sync', $organisation),
            ],
            'canRecord' => $request->user()->hasOrganisationPermission($organisation, 'daily_records.create'),
            'canVaccinate' => $request->user()->hasOrganisationPermission($organisation, 'vaccinations.mark_done'),
        ]);
    }

    public function flocks(Request $request, Organisation $organisation): JsonResponse
    {
        /** @var OrganisationUser $membership */
        $membership = $request->attributes->get('membership');

        $flocks = $this->processor->accessibleFlocks($organisation, $membership)
            ->with(['farm:id,name', 'chickenType:id,name,production_kind'])
            ->orderBy('flocks.code')
            ->get()
            ->map(fn (Flock $flock): array => [
                'id' => $flock->id,
                'code' => $flock->code,
                'name' => $flock->name,
                'farm' => $flock->farm?->name,
                'kind' => $flock->productionKind()->value,
                'tracks_eggs' => $flock->productionKind()->tracksEggs(),
                'tracks_weight' => $flock->productionKind()->tracksWeight(),
                'quantity_current' => (int) $flock->quantity_current,
                'age_days' => $flock->getCurrentAgeInDays(),
                'last_record_date' => $flock->dailyRecords()->max('record_date'),
            ]);

        return response()->json(['flocks' => $flocks, 'generated_at' => now()->toIso8601String()]);
    }

    public function vaccinations(Request $request, Organisation $organisation): JsonResponse
    {
        /** @var OrganisationUser $membership */
        $membership = $request->attributes->get('membership');

        $flockIds = $this->processor->accessibleFlocks($organisation, $membership)->pluck('flocks.id');

        $vaccinations = Vaccination::query()
            ->whereIn('flock_id', $flockIds)
            ->whereIn('status', [VaccinationStatus::Pending->value, VaccinationStatus::Delayed->value])
            ->whereDate('scheduled_date', '<=', now()->addDays(14)->toDateString())
            ->with('flock:id,code,quantity_current')
            ->orderBy('scheduled_date')
            ->get()
            ->map(fn (Vaccination $vaccination): array => [
                'id' => $vaccination->id,
                'flock_id' => $vaccination->flock_id,
                'flock_code' => $vaccination->flock?->code,
                'treatment_name' => $vaccination->treatment_name,
                'treatment_type' => $vaccination->treatment_type,
                'scheduled_date' => $vaccination->scheduled_date?->toDateString(),
                'overdue' => $vaccination->isOverdue(),
                'birds' => (int) ($vaccination->flock?->quantity_current ?? 0),
            ]);

        return response()->json(['vaccinations' => $vaccinations, 'generated_at' => now()->toIso8601String()]);
    }

    public function csrf(Organisation $organisation): JsonResponse
    {
        return response()->json(['token' => csrf_token()]);
    }

    public function sync(SyncRequest $request, Organisation $organisation): JsonResponse
    {
        /** @var OrganisationUser $membership */
        $membership = $request->attributes->get('membership');

        $results = $this->processor->process($organisation, $request->user(), $membership, $request->validated('entries'));

        return response()->json(['results' => $results, 'synced_at' => now()->toIso8601String()]);
    }

    /**
     * Liste des assets Vite à pré-cacher par le service worker.
     */
    public function assets(): JsonResponse
    {
        $manifestPath = public_path('build/manifest.json');
        $assets = [];

        if (File::exists($manifestPath)) {
            foreach (json_decode(File::get($manifestPath), true) ?? [] as $entry) {
                if (isset($entry['file'])) {
                    $assets[] = '/build/'.$entry['file'];
                }

                foreach ($entry['css'] ?? [] as $css) {
                    $assets[] = '/build/'.$css;
                }
            }
        }

        return response()->json(['assets' => array_values(array_unique($assets)), 'version' => File::exists($manifestPath) ? md5_file($manifestPath) : 'dev']);
    }
}
