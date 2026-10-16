<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProductionKind;
use App\Models\Organisation;
use App\Services\FarmComparisonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Comparaison multi-fermes pour le mobile : indicateurs par ferme et scores normalisés sur une
 * période, pour 2 à 5 fermes accessibles au membre.
 */
class CompareController extends ApiController
{
    public function index(Request $request, Organisation $organisation, FarmComparisonService $service): JsonResponse
    {
        $membership = $this->membership($request);
        $this->authorizePermission($membership, 'daily_records.view');

        $validated = $request->validate([
            'farm_ids' => ['required', 'array', 'min:'.FarmComparisonService::MIN_FARMS, 'max:'.FarmComparisonService::MAX_FARMS],
            'farm_ids.*' => ['integer', 'distinct'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'production_kind' => ['nullable', Rule::enum(ProductionKind::class)],
        ]);

        $farmIds = array_map(intval(...), $validated['farm_ids']);

        foreach ($farmIds as $farmId) {
            $this->authorizeFarm($membership, $farmId);
        }

        $kind = isset($validated['production_kind']) ? ProductionKind::from($validated['production_kind']) : null;
        $rows = $service->compare($organisation->id, $farmIds, $validated['date_from'], $validated['date_to'], $kind);
        $scores = $service->scores($rows, $kind);

        return response()->json([
            'period' => ['date_from' => $validated['date_from'], 'date_to' => $validated['date_to']],
            'production_kind' => $kind?->value,
            'axes' => $scores['axes'],
            'farms' => $rows->map(fn (array $row): array => $row + ['scores' => $scores['scores'][$row['farm_id']] ?? []])->values(),
        ]);
    }
}
