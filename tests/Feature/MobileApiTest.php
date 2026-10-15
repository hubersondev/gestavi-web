<?php

use App\Enums\PlanFeature;
use App\Enums\SaleStatus;
use App\Enums\VaccinationStatus;
use App\Models\Building;
use App\Models\Customer;
use App\Models\DailyRecord;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Stock;
use App\Models\StockCategory;
use App\Models\User;
use App\Models\Vaccination;
use App\Services\AccountService;
use Database\Seeders\ChartAccountSeeder;
use Database\Seeders\ChickenTypeSeeder;
use Database\Seeders\OrganisationPermissionSeeder;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * API v1 pour l'application mobile : jetons, appartenances, fermes, bandes, synchronisation,
 * ventes, stocks, notifications ; cloisonnement par organisation et par ferme, permissions.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);
    $this->seed(ChartAccountSeeder::class);
    $this->seed(ChickenTypeSeeder::class);
    $plan = Plan::factory()->create(['features' => collect(PlanFeature::cases())->map->value->all()]);
    $this->organisation = Organisation::factory()->create(['plan_id' => $plan->id]);
    $this->farmA = Farm::factory()->create(['organisation_id' => $this->organisation->id, 'name' => 'Ferme A']);
    $this->farmB = Farm::factory()->create(['organisation_id' => $this->organisation->id, 'name' => 'Ferme B']);
    $buildingA = Building::factory()->create(['farm_id' => $this->farmA->id]);
    $buildingB = Building::factory()->create(['farm_id' => $this->farmB->id]);
    $this->flockA = Flock::factory()->active()->create(['building_id' => $buildingA->id, 'quantity_started' => 1000, 'quantity_current' => 1000, 'start_date' => now()->subDays(20)]);
    $this->flockB = Flock::factory()->active()->create(['building_id' => $buildingB->id, 'quantity_started' => 500, 'quantity_current' => 500, 'start_date' => now()->subDays(10)]);

    $this->owner = User::factory()->create(['email' => 'owner@api.test', 'password' => 'password']);
    $this->organisation->addMember($this->owner, 'owner');

    $this->employee = User::factory()->create(['email' => 'employee@api.test', 'password' => 'password']);
    $this->organisation->addMember($this->employee, 'employee');
    $this->employee->membershipIn($this->organisation)->farms()->attach($this->farmA->id);

    $this->base = '/api/v1/organisations/'.$this->organisation->slug;
});

it('délivre un jeton par appareil, décrit l\'utilisateur et ses appartenances, puis révoque le jeton', function () {
    postJson('/api/v1/auth/token', ['email' => 'owner@api.test', 'password' => 'faux', 'device_name' => 'Téléphone'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    $response = postJson('/api/v1/auth/token', ['email' => 'owner@api.test', 'password' => 'password', 'device_name' => 'Téléphone de Jean'])
        ->assertCreated()
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'organisations']]);

    $token = $response->json('token');

    expect($this->owner->tokens()->count())->toBe(1)
        ->and($this->owner->tokens()->first()->name)->toBe('Téléphone de Jean');

    $me = getJson('/api/v1/me', ['Authorization' => "Bearer {$token}"])->assertOk();

    expect($me->json('data.organisations.0.slug'))->toBe($this->organisation->slug)
        ->and($me->json('data.organisations.0.role'))->toBe('owner')
        ->and($me->json('data.organisations.0.all_farms'))->toBeTrue()
        ->and($me->json('data.organisations.0.permissions'))->toContain('sales.create');

    deleteJson('/api/v1/auth/token', [], ['Authorization' => "Bearer {$token}"])->assertOk();

    expect($this->owner->tokens()->count())->toBe(0);
    // Dans un même processus de test, le garde conserve l'utilisateur résolu : on l'oublie pour rejouer l'authentification
    app('auth')->forgetGuards();
    getJson('/api/v1/me', ['Authorization' => "Bearer {$token}"])->assertUnauthorized();
});

it('cloisonne fermes et bandes aux affectations du membre et refuse les autres organisations', function () {
    Sanctum::actingAs($this->employee);

    $farms = getJson("{$this->base}/farms")->assertOk();
    expect($farms->json('data'))->toHaveCount(1)
        ->and($farms->json('data.0.name'))->toBe('Ferme A');

    $flocks = getJson("{$this->base}/flocks")->assertOk();
    expect(collect($flocks->json('data'))->pluck('id')->all())->toBe([$this->flockA->id]);

    getJson("{$this->base}/flocks/{$this->flockB->id}")->assertForbidden();

    $other = Organisation::factory()->create();
    getJson('/api/v1/organisations/'.$other->slug.'/farms')->assertForbidden();
    getJson('/api/v1/organisations/inconnue/farms')->assertNotFound();

    Sanctum::actingAs($this->owner);
    expect(getJson("{$this->base}/flocks")->assertOk()->json('data'))->toHaveCount(2);
});

it('synchronise des relevés hors-ligne de façon idempotente puis les relit', function () {
    Sanctum::actingAs($this->employee);
    $uuid = (string) Str::uuid();
    $entries = ['entries' => [[
        'client_uuid' => $uuid,
        'type' => 'daily_record',
        'recorded_at' => now()->toIso8601String(),
        'payload' => ['flock_id' => $this->flockA->id, 'record_date' => now()->toDateString(), 'mortality' => 3, 'eggs_collected' => 0, 'feed_consumed_kg' => 120],
    ]]];

    $first = postJson("{$this->base}/sync", $entries)->assertOk();
    expect($first->json('results.0.status'))->toBe('created');

    $second = postJson("{$this->base}/sync", $entries)->assertOk();
    expect($second->json('results.0.status'))->toBe('duplicate')
        ->and($this->flockA->dailyRecords()->count())->toBe(1)
        ->and((int) $this->flockA->fresh()->quantity_current)->toBe(997);

    $records = getJson("{$this->base}/daily-records?flock_id={$this->flockA->id}")->assertOk();
    expect($records->json('data'))->toHaveCount(1)
        ->and($records->json('data.0.mortality'))->toBe(3);
});

it('crée une vente encaissée depuis le mobile, sans doublon au rejeu, et refuse sans permission', function () {
    Sanctum::actingAs($this->owner);
    $customer = Customer::create(['organisation_id' => $this->organisation->id, 'name' => 'Maquis Chez Tantie', 'type' => 'restaurant']);
    app(AccountService::class)->createAccount(['organisation_id' => $this->organisation->id, 'name' => 'Caisse', 'type' => 'cash', 'initial_balance' => 0]);

    $payload = [
        'farm_id' => $this->farmA->id,
        'customer_id' => $customer->id,
        'sale_date' => now()->toDateString(),
        'client_uuid' => (string) Str::uuid(),
        'items' => [['product_name' => 'Plateau de 30 œufs', 'quantity' => 10, 'unit_price' => 3000]],
        'payment' => ['method' => 'cash'],
    ];

    $created = postJson("{$this->base}/sales", $payload)->assertCreated();

    expect($created->json('data.total'))->toBe(30000)
        ->and($created->json('data.status'))->toBe(SaleStatus::Paid->value)
        ->and($created->json('data.items'))->toHaveCount(1)
        ->and($created->json('data.payments'))->toHaveCount(1);

    $replayed = postJson("{$this->base}/sales", $payload)->assertOk();
    expect($replayed->json('data.id'))->toBe($created->json('data.id'))
        ->and(Sale::query()->where('organisation_id', $this->organisation->id)->count())->toBe(1);

    getJson("{$this->base}/sales/{$created->json('data.id')}")->assertOk()->assertJsonPath('data.customer', 'Maquis Chez Tantie');
    expect(getJson("{$this->base}/sales?status=paid")->assertOk()->json('data'))->toHaveCount(1);

    Sanctum::actingAs($this->employee);
    postJson("{$this->base}/sales", $payload + ['client_uuid' => (string) Str::uuid()])->assertForbidden();
    postJson("{$this->base}/sales", ['farm_id' => $this->farmB->id] + $payload)->assertForbidden();
});

it('liste les stocks et enregistre un mouvement de sortie idempotent', function () {
    Sanctum::actingAs($this->owner);
    $category = StockCategory::create(['organisation_id' => $this->organisation->id, 'name' => 'Aliment', 'type' => 'feed']);
    $stock = Stock::create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farmA->id, 'stock_category_id' => $category->id, 'name' => 'Aliment démarrage', 'unit' => 'sac', 'quantity' => 40, 'min_quantity' => 10, 'unit_price' => 18500, 'is_active' => true]);

    $stocks = getJson("{$this->base}/stocks")->assertOk();
    expect($stocks->json('data.0.quantity'))->toBe(40)
        ->and($stocks->json('data.0.category'))->toBe('Aliment');

    $uuid = (string) Str::uuid();
    $movement = ['stock_id' => $stock->id, 'direction' => 'out', 'quantity' => 5, 'flock_id' => $this->flockA->id, 'client_uuid' => $uuid];

    postJson("{$this->base}/stock-movements", $movement)->assertCreated()->assertJsonPath('status', 'created')->assertJsonPath('stock.quantity', 35);
    postJson("{$this->base}/stock-movements", $movement)->assertOk()->assertJsonPath('status', 'duplicate')->assertJsonPath('stock.quantity', 35);

    postJson("{$this->base}/stock-movements", ['stock_id' => $stock->id, 'direction' => 'out', 'quantity' => 1000])->assertStatus(422);
});

it('expose les notifications de l\'utilisateur et permet de les marquer lues', function () {
    Sanctum::actingAs($this->owner);
    $this->owner->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'App\\Notifications\\Alerte', 'data' => ['title' => 'Stock bas', 'body' => 'Aliment démarrage sous le seuil.']]);

    $list = getJson('/api/v1/notifications?unread=1')->assertOk();
    expect($list->json('data'))->toHaveCount(1)
        ->and($list->json('data.0.title'))->toBe('Stock bas');

    postJson('/api/v1/notifications/'.$list->json('data.0.id').'/read')->assertOk();
    expect(getJson('/api/v1/notifications?unread=1')->json('data'))->toHaveCount(0);
});

it('répond en JSON 401 à un navigateur sans jeton plutôt que de chercher une route login', function () {
    $this->get('/api/v1/me', ['Accept' => 'text/html'])
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonPath('message', 'Unauthenticated.');
});

it('exige la bande pour des sujets vivants, décrémente son effectif et encaisse dans la caisse par défaut de la ferme', function () {
    Sanctum::actingAs($this->owner);
    $caisse = app(AccountService::class)->createAccount(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farmA->id, 'name' => 'Caisse Ferme A', 'type' => 'cash', 'initial_balance' => 0, 'is_default' => true]);

    $base = ['farm_id' => $this->farmA->id, 'sale_date' => now()->toDateString()];

    postJson("{$this->base}/sales", $base + ['items' => [['product_name' => 'Poulet vif', 'quantity' => 50, 'unit_price' => 3000, 'is_live_birds' => true]]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items.0.flock_id']);

    postJson("{$this->base}/sales", $base + ['items' => [['product_name' => 'Poulet vif', 'quantity' => 50, 'unit_price' => 3000, 'is_live_birds' => true, 'flock_id' => $this->flockB->id]]])
        ->assertStatus(422);

    $created = postJson("{$this->base}/sales", $base + [
        'items' => [['product_name' => 'Poulet vif', 'quantity' => 50, 'unit_price' => 3000, 'is_live_birds' => true, 'flock_id' => $this->flockA->id]],
        'payment' => ['method' => 'orange_money', 'amount' => 100000],
    ])->assertCreated();

    expect($this->flockA->fresh()->quantity_current)->toBe(950)
        ->and($created->json('data.status'))->toBe(SaleStatus::Partial->value)
        ->and($created->json('data.payments.0.account_id'))->toBe($caisse->id)
        ->and($caisse->fresh()->current_balance)->toEqual(100000);
});

it('liste les vaccinations à faire des fermes accessibles et valide une vaccination sans doublon', function () {
    $due = Vaccination::factory()->create(['flock_id' => $this->flockA->id, 'treatment_name' => 'Gumboro', 'status' => VaccinationStatus::Pending, 'scheduled_date' => now()->subDay()]);
    Vaccination::factory()->create(['flock_id' => $this->flockA->id, 'treatment_name' => 'Newcastle', 'status' => VaccinationStatus::Done, 'scheduled_date' => now()->subDays(5), 'administered_date' => now()->subDays(5)]);
    Vaccination::factory()->create(['flock_id' => $this->flockB->id, 'treatment_name' => 'Bronchite', 'status' => VaccinationStatus::Delayed, 'scheduled_date' => now()->addDays(2)]);

    Sanctum::actingAs($this->owner);
    $all = getJson("{$this->base}/vaccinations")->assertOk();
    expect($all->json('data'))->toHaveCount(2)
        ->and($all->json('data.0.treatment_name'))->toBe('Gumboro')
        ->and($all->json('data.0.is_overdue'))->toBeTrue()
        ->and($all->json('data.0.flock_code'))->toBe($this->flockA->code);

    Sanctum::actingAs($this->employee);
    expect(getJson("{$this->base}/vaccinations")->assertOk()->json('data'))->toHaveCount(1);
    getJson("{$this->base}/vaccinations?farm_id={$this->farmB->id}")->assertForbidden();

    Sanctum::actingAs($this->owner);
    $uuid = (string) Str::uuid();
    $entry = ['client_uuid' => $uuid, 'type' => 'vaccination_done', 'payload' => ['vaccination_id' => $due->id, 'administered_date' => now()->toDateString(), 'birds_treated' => 980]];
    postJson("{$this->base}/sync", ['entries' => [$entry]])->assertOk()->assertJsonPath('results.0.status', 'created');
    postJson("{$this->base}/sync", ['entries' => [$entry]])->assertOk()->assertJsonPath('results.0.status', 'duplicate');

    expect($due->fresh()->status)->toBe(VaccinationStatus::Done)
        ->and($due->fresh()->birds_treated)->toBe(980)
        ->and(getJson("{$this->base}/vaccinations")->json('data'))->toHaveCount(1);
});

it('encaisse une vente existante depuis le mobile sans doublon au rejeu', function () {
    Sanctum::actingAs($this->owner);
    app(AccountService::class)->createAccount(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farmA->id, 'name' => 'Caisse A', 'type' => 'cash', 'initial_balance' => 0, 'is_default' => true]);
    $sale = postJson("{$this->base}/sales", ['farm_id' => $this->farmA->id, 'sale_date' => now()->toDateString(), 'items' => [['product_name' => 'Plateau', 'quantity' => 10, 'unit_price' => 3000]]])->assertCreated();
    $id = $sale->json('data.id');

    $payment = ['amount' => 10000, 'method' => 'wave', 'client_uuid' => (string) Str::uuid()];
    postJson("{$this->base}/sales/{$id}/payments", $payment)->assertCreated()->assertJsonPath('data.status', SaleStatus::Partial->value)->assertJsonPath('data.paid', 10000);
    postJson("{$this->base}/sales/{$id}/payments", $payment)->assertOk()->assertJsonPath('data.paid', 10000);

    expect(Sale::query()->find($id)->payments()->count())->toBe(1)
        ->and(Sale::query()->find($id)->payments()->first()->is_recorded)->toBeTrue();

    postJson("{$this->base}/sales/{$id}/payments", ['amount' => 50000, 'method' => 'cash'])->assertStatus(422);
});

it('complète le relevé du jour existant quand le mobile l\'a pré-rempli, et le protège sinon', function () {
    Sanctum::actingAs($this->owner);
    $date = now()->toDateString();
    $entry = fn (array $payload) => ['client_uuid' => (string) Str::uuid(), 'type' => 'daily_record', 'payload' => ['flock_id' => $this->flockA->id, 'record_date' => $date] + $payload];

    postJson("{$this->base}/sync", ['entries' => [$entry(['mortality' => 3, 'eggs_collected' => 400])]])->assertOk()->assertJsonPath('results.0.status', 'created');

    // Sans pré-remplissage : la version en ligne est protégée
    postJson("{$this->base}/sync", ['entries' => [$entry(['mortality' => 10, 'eggs_collected' => 850])]])->assertOk()->assertJsonPath('results.0.status', 'duplicate');
    expect($this->flockA->fresh()->quantity_current)->toBe(997);

    // Pré-rempli puis complété : les totaux du jour remplacent les valeurs, l'effectif suit l'écart
    $merged = postJson("{$this->base}/sync", ['entries' => [$entry(['mortality' => 5, 'eggs_collected' => 850, 'merge' => true])]])->assertOk();
    expect($merged->json('results.0.status'))->toBe('created')
        ->and($merged->json('results.0.message'))->toContain('complété');

    $record = DailyRecord::query()->where('flock_id', $this->flockA->id)->whereDate('record_date', $date)->sole();
    expect((int) $record->mortality)->toBe(5)
        ->and((int) $record->eggs_collected)->toBe(850)
        ->and($this->flockA->fresh()->quantity_current)->toBe(995)
        ->and(DailyRecord::query()->where('flock_id', $this->flockA->id)->count())->toBe(1);

    // Des pertes au-delà de l'effectif restent refusées, même en complément
    postJson("{$this->base}/sync", ['entries' => [$entry(['mortality' => 5000, 'merge' => true])]])->assertOk()->assertJsonPath('results.0.status', 'rejected');
});

it('refuse une vente d\'œufs au-delà du stock de la ferme et expose la conversion des produits et le stock d\'œufs', function () {
    Sanctum::actingAs($this->owner);
    $trays = Product::query()->where('organisation_id', $this->organisation->id)->where('code', 'OEUF-P30')->firstOrFail();

    $products = getJson("{$this->base}/products")->assertOk()->json('data');
    $tray = collect($products)->firstWhere('code', 'OEUF-P30');
    expect($tray['stock_units_per_unit'])->toEqual(30)
        ->and($tray['stock_kind'])->toBe('eggs');

    $eggStock = collect(getJson("{$this->base}/stocks?farm_id={$this->farmA->id}")->assertOk()->json('data'))->firstWhere('kind', 'eggs');
    expect($eggStock)->not->toBeNull()
        ->and($eggStock['quantity'])->toEqual(0);

    $payload = ['farm_id' => $this->farmA->id, 'sale_date' => now()->toDateString(), 'items' => [['product_id' => $trays->id, 'product_name' => $trays->name, 'quantity' => 2, 'unit_price' => 3000]]];
    $refused = postJson("{$this->base}/sales", $payload)->assertStatus(422);
    expect($refused->json('errors.sale.0'))->toContain('Stock insuffisant');

    DailyRecord::create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farmA->id, 'flock_id' => $this->flockA->id, 'record_date' => now()->toDateString(), 'mortality' => 0, 'culls' => 0, 'eggs_collected' => 90]);

    postJson("{$this->base}/sales", $payload)->assertCreated();
    expect(collect(getJson("{$this->base}/stocks?farm_id={$this->farmA->id}")->json('data'))->firstWhere('kind', 'eggs')['quantity'])->toEqual(30);
});
