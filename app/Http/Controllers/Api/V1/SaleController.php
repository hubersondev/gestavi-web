<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Exceptions\CommercialException;
use App\Http\Requests\Api\V1\StorePaymentRequest;
use App\Http\Requests\Api\V1\StoreSaleRequest;
use App\Http\Resources\Api\V1\SaleResource;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Flock;
use App\Models\OfflineSyncEntry;
use App\Models\Organisation;
use App\Models\Product;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Ventes depuis le mobile : liste incrémentale, détail, création (avec encaissement immédiat
 * possible) et encaissement sur une facture existante. Mêmes services et permissions que le panel.
 */
class SaleController extends ApiController
{
    public function __construct(private readonly SaleService $sales) {}

    public function index(Request $request, Organisation $organisation): AnonymousResourceCollection
    {
        $membership = $this->membership($request);
        $this->authorizePermission($membership, 'sales.view');

        $query = Sale::query()
            ->where('sales.organisation_id', $organisation->id)
            ->with(['customer:id,name'])
            ->orderByDesc('sales.sale_date')
            ->orderByDesc('sales.id');

        if ($request->filled('since')) {
            $query->where('sales.updated_at', '>=', Carbon::parse($request->string('since')->toString()));
        }

        if ($request->filled('status')) {
            $query->where('sales.status', $request->string('status')->toString());
        }

        $this->scopeToFarms($query, $membership, 'sales.farm_id');

        return SaleResource::collection($query->paginate($this->perPage($request, 50, 200)));
    }

    public function show(Request $request, Organisation $organisation, Sale $sale): SaleResource
    {
        $membership = $this->membership($request);
        $this->authorizePermission($membership, 'sales.view');
        abort_if($sale->organisation_id !== $organisation->id, 404);
        $this->authorizeFarm($membership, $sale->farm_id);

        return new SaleResource($sale->load(['customer:id,name', 'items', 'payments']));
    }

    public function store(StoreSaleRequest $request, Organisation $organisation): JsonResponse
    {
        $membership = $this->membership($request);
        $this->authorizePermission($membership, 'sales.create');

        $data = $request->validated();
        $this->authorizeFarm($membership, (int) $data['farm_id']);

        // Rejeu d'une saisie hors-ligne déjà reçue : même facture, sans doublon
        if (! empty($data['client_uuid'])) {
            $existing = OfflineSyncEntry::query()->where('client_uuid', $data['client_uuid'])->first();

            if ($existing && $existing->subject_type === Sale::class && $existing->subject_id) {
                $sale = Sale::query()->find($existing->subject_id);

                if ($sale) {
                    return (new SaleResource($sale->load(['customer:id,name', 'items', 'payments'])))->response()->setStatusCode(200);
                }
            }
        }

        if (! empty($data['customer_id'])) {
            abort_unless(Customer::query()->where('organisation_id', $organisation->id)->whereKey($data['customer_id'])->exists(), 422, 'Client inconnu dans cette organisation.');
        }

        $this->assertFlocksBelongToFarm($organisation, (int) $data['farm_id'], $data['items']);

        // Unité de chaque ligne : celle fournie, sinon celle du produit du catalogue, sinon « unité »
        $productUnits = Product::query()
            ->where('organisation_id', $organisation->id)
            ->whereIn('id', array_filter(array_column($data['items'], 'product_id')))
            ->pluck('unit', 'id')
            ->all();

        try {
            $sale = $this->sales->createSale([
                'organisation_id' => $organisation->id,
                'farm_id' => (int) $data['farm_id'],
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => $request->user()->id,
                'sale_date' => $data['sale_date'],
                'due_date' => $data['due_date'] ?? null,
                'status' => SaleStatus::Pending,
                'tax_percent' => (float) ($data['tax_percent'] ?? 0),
                'notes' => $data['notes'] ?? null,
            ], array_map(fn (array $item): array => [
                'product_id' => $item['product_id'] ?? null,
                'product_name' => $item['product_name'],
                'unit' => $item['unit'] ?? ($productUnits[$item['product_id'] ?? 0] ?? 'unité'),
                'quantity' => (float) $item['quantity'],
                'unit_price' => (float) $item['unit_price'],
                'flock_id' => $item['flock_id'] ?? null,
                'is_live_birds' => (bool) ($item['is_live_birds'] ?? false),
            ], $data['items']));

            if (! empty($data['payment'])) {
                $this->authorizePermission($membership, 'sales.record_payment');
                $sale = $sale->fresh();

                $this->sales->recordPayment(
                    $sale,
                    (float) ($data['payment']['amount'] ?? $sale->total),
                    PaymentMethod::from($data['payment']['method']),
                    $data['payment']['account_id'] ?? $this->defaultAccountId($organisation, $sale->farm_id),
                    $data['payment']['reference'] ?? null,
                    Carbon::parse($data['sale_date']),
                );
            }
        } catch (CommercialException $e) {
            throw ValidationException::withMessages(['sale' => $e->getMessage()]);
        }

        if (! empty($data['client_uuid'])) {
            OfflineSyncEntry::query()->create([
                'organisation_id' => $organisation->id,
                'user_id' => $request->user()->id,
                'client_uuid' => $data['client_uuid'],
                'type' => 'sale',
                'payload' => $data,
                'status' => OfflineSyncEntry::STATUS_CREATED,
                'message' => 'Vente enregistrée.',
                'subject_type' => Sale::class,
                'subject_id' => $sale->id,
                'recorded_at' => Carbon::parse($data['sale_date']),
                'processed_at' => now(),
            ]);
        }

        return (new SaleResource($sale->fresh()->load(['customer:id,name', 'items', 'payments'])))->response()->setStatusCode(201);
    }

    public function storePayment(StorePaymentRequest $request, Organisation $organisation, Sale $sale): SaleResource
    {
        $membership = $this->membership($request);
        $this->authorizePermission($membership, 'sales.record_payment');
        abort_if($sale->organisation_id !== $organisation->id, 404);
        $this->authorizeFarm($membership, $sale->farm_id);

        $data = $request->validated();

        try {
            $this->sales->recordPayment(
                $sale,
                (float) $data['amount'],
                PaymentMethod::from($data['method']),
                $data['account_id'] ?? $this->defaultAccountId($organisation, $sale->farm_id),
                $data['reference'] ?? null,
                isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : null,
            );
        } catch (CommercialException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return new SaleResource($sale->fresh()->load(['customer:id,name', 'items', 'payments']));
    }

    /**
     * Compte d'encaissement quand le mobile n'en précise pas : le compte par défaut de la ferme,
     * sinon celui de l'organisation, pour que l'argent reçu apparaisse bien en trésorerie.
     */
    private function defaultAccountId(Organisation $organisation, int $farmId): ?int
    {
        return Account::query()
            ->forOrganisation($organisation->id)
            ->active()
            ->where(fn ($query) => $query->where('farm_id', $farmId)->orWhereNull('farm_id'))
            ->orderByDesc('is_default')
            ->orderByRaw('farm_id IS NULL')
            ->value('id');
    }

    /**
     * Les bandes citées par les lignes doivent appartenir à la ferme qui vend.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function assertFlocksBelongToFarm(Organisation $organisation, int $farmId, array $items): void
    {
        $flockIds = collect($items)->pluck('flock_id')->filter()->unique()->values();

        if ($flockIds->isEmpty()) {
            return;
        }

        $known = Flock::query()
            ->whereIn('id', $flockIds)
            ->whereHas('building.farm', fn ($query) => $query->where('organisation_id', $organisation->id)->where('id', $farmId))
            ->count();

        abort_if($known !== $flockIds->count(), 422, 'Une bande citée n\'appartient pas à la ferme qui vend.');
    }
}
