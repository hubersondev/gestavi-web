<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StockMovementReason;
use App\Exceptions\StockException;
use App\Http\Requests\Api\V1\StoreStockMovementRequest;
use App\Http\Resources\Api\V1\StockResource;
use App\Models\OfflineSyncEntry;
use App\Models\Organisation;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Stocks des fermes du membre et saisie d'un mouvement (entrée ou sortie), idempotente par client_uuid.
 */
class StockController extends ApiController
{
    public function index(Request $request, Organisation $organisation): AnonymousResourceCollection
    {
        $membership = $this->membership($request);
        $this->authorizePermission($membership, 'stocks.view');

        $query = Stock::query()
            ->where('stocks.organisation_id', $organisation->id)
            ->where('stocks.is_active', true)
            ->with('category:id,name,type')
            ->orderBy('stocks.name');

        if ($request->filled('farm_id')) {
            $this->authorizeFarm($membership, (int) $request->integer('farm_id'));
            $query->where('stocks.farm_id', (int) $request->integer('farm_id'));
        }

        if ($request->filled('since')) {
            $query->where('stocks.updated_at', '>=', Carbon::parse($request->string('since')->toString()));
        }

        $this->scopeToFarms($query, $membership, 'stocks.farm_id');

        return StockResource::collection($query->paginate($this->perPage($request, 100, 500)));
    }

    public function storeMovement(StoreStockMovementRequest $request, Organisation $organisation): JsonResponse
    {
        $membership = $this->membership($request);
        $this->authorizePermission($membership, 'stocks.record_movement');

        $data = $request->validated();

        $stock = Stock::query()->where('organisation_id', $organisation->id)->whereKey($data['stock_id'])->first();
        abort_if($stock === null, 422, 'Article de stock inconnu dans cette organisation.');
        $this->authorizeFarm($membership, $stock->farm_id);

        if (! empty($data['client_uuid'])) {
            $existing = OfflineSyncEntry::query()->where('client_uuid', $data['client_uuid'])->first();

            if ($existing) {
                return response()->json([
                    'status' => OfflineSyncEntry::STATUS_DUPLICATE,
                    'movement_id' => $existing->subject_id,
                    'stock' => new StockResource($stock->fresh()->load('category:id,name,type')),
                ]);
            }
        }

        $reason = isset($data['reason']) ? StockMovementReason::from($data['reason']) : null;

        try {
            /** @var StockMovement $movement */
            $movement = $data['direction'] === 'in'
                ? $stock->recordIn((float) $data['quantity'], $reason ?? StockMovementReason::Purchase, isset($data['unit_price']) ? (float) $data['unit_price'] : null, null, $data['flock_id'] ?? null, $data['reference'] ?? null, $data['notes'] ?? null)
                : $stock->recordOut((float) $data['quantity'], $reason ?? StockMovementReason::Consumption, $data['flock_id'] ?? null, $data['reference'] ?? null, $data['notes'] ?? null);
        } catch (StockException $e) {
            throw ValidationException::withMessages(['quantity' => $e->getMessage()]);
        }

        if (! empty($data['client_uuid'])) {
            OfflineSyncEntry::query()->create([
                'organisation_id' => $organisation->id,
                'user_id' => $request->user()->id,
                'client_uuid' => $data['client_uuid'],
                'type' => 'stock_movement',
                'payload' => $data,
                'status' => OfflineSyncEntry::STATUS_CREATED,
                'message' => 'Mouvement enregistré.',
                'subject_type' => StockMovement::class,
                'subject_id' => $movement->id,
                'recorded_at' => now(),
                'processed_at' => now(),
            ]);
        }

        return response()->json([
            'status' => OfflineSyncEntry::STATUS_CREATED,
            'movement_id' => $movement->id,
            'stock' => new StockResource($stock->fresh()->load('category:id,name,type')),
        ], 201);
    }
}
