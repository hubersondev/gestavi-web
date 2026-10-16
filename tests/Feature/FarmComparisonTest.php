<?php

use App\Enums\ExpenseStatus;
use App\Enums\PlanFeature;
use App\Enums\ProductionKind;
use App\Enums\SaleStatus;
use App\Filament\Farm\Pages\Reports\FarmComparisonPage;
use App\Models\Building;
use App\Models\ChickenType;
use App\Models\DailyRecord;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\Sale;
use App\Models\User;
use App\Services\FarmComparisonService;
use Database\Seeders\ChickenTypeSeeder;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

/**
 * Comparaison multi-fermes : indicateurs par ferme sur une période, filtre par type de production,
 * scores normalisés, page (fermes comparables, export) et API (accès par ferme, plan, validation).
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);
    $this->seed(ChickenTypeSeeder::class);

    $plan = Plan::factory()->create(['features' => collect(PlanFeature::cases())->map->value->all()]);
    $this->organisation = Organisation::factory()->create(['plan_id' => $plan->id]);
    $this->owner = User::factory()->create();
    $this->organisation->addMember($this->owner, 'owner');
    $this->employee = User::factory()->create();
    $this->organisation->addMember($this->employee, 'employee');

    $this->farmA = Farm::factory()->create(['organisation_id' => $this->organisation->id, 'name' => 'Ferme A']);
    $this->farmB = Farm::factory()->create(['organisation_id' => $this->organisation->id, 'name' => 'Ferme B']);
    $this->farmC = Farm::factory()->create(['organisation_id' => $this->organisation->id, 'name' => 'Ferme C']);
    $this->employee->membershipIn($this->organisation)->farms()->attach([$this->farmA->id, $this->farmC->id]);

    $broiler = ChickenType::query()->where('production_kind', ProductionKind::Broiler)->firstOrFail();
    $layer = ChickenType::query()->where('production_kind', ProductionKind::Layer)->firstOrFail();

    $buildingA = Building::factory()->create(['farm_id' => $this->farmA->id]);
    $buildingB = Building::factory()->create(['farm_id' => $this->farmB->id]);
    $this->flockA = Flock::factory()->active()->create(['building_id' => $buildingA->id, 'chicken_type_id' => $broiler->id, 'quantity_started' => 1000, 'quantity_current' => 1000, 'start_date' => now()->subDays(30)]);
    $this->flockB = Flock::factory()->active()->create(['building_id' => $buildingB->id, 'chicken_type_id' => $layer->id, 'quantity_started' => 500, 'quantity_current' => 500, 'start_date' => now()->subDays(200)]);

    // Chair (ferme A) : 20 morts, 200 kg d'aliment, poids 1 500 puis 1 600 g
    DailyRecord::create(['flock_id' => $this->flockA->id, 'record_date' => now()->subDays(2)->toDateString(), 'mortality' => 10, 'feed_consumed_kg' => 100, 'average_weight_g' => 1500]);
    DailyRecord::create(['flock_id' => $this->flockA->id, 'record_date' => now()->subDay()->toDateString(), 'mortality' => 10, 'feed_consumed_kg' => 100, 'average_weight_g' => 1600]);
    // Pondeuses (ferme B) : 820 œufs, 5 morts, 120 kg d'aliment
    DailyRecord::create(['flock_id' => $this->flockB->id, 'record_date' => now()->subDays(2)->toDateString(), 'mortality' => 5, 'eggs_collected' => 400, 'feed_consumed_kg' => 60]);
    DailyRecord::create(['flock_id' => $this->flockB->id, 'record_date' => now()->subDay()->toDateString(), 'mortality' => 0, 'eggs_collected' => 420, 'feed_consumed_kg' => 60]);
    // Hors période
    DailyRecord::create(['flock_id' => $this->flockA->id, 'record_date' => now()->subDays(20)->toDateString(), 'mortality' => 50]);

    Sale::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farmA->id, 'status' => SaleStatus::Pending, 'total' => 500000, 'sale_date' => now()->subDay()]);
    Sale::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farmA->id, 'status' => SaleStatus::Cancelled, 'total' => 999999, 'sale_date' => now()->subDay()]);
    Sale::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farmB->id, 'status' => SaleStatus::Paid, 'total' => 200000, 'paid' => 200000, 'sale_date' => now()]);
    Expense::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farmA->id, 'status' => ExpenseStatus::Paid, 'amount' => 100000, 'expense_date' => now()->subDay()]);
    Expense::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farmB->id, 'status' => ExpenseStatus::Cancelled, 'amount' => 777777, 'expense_date' => now()]);

    $this->period = ['date_from' => now()->subDays(6)->toDateString(), 'date_to' => now()->toDateString()];
    $this->service = app(FarmComparisonService::class);
});

it('calcule les indicateurs de chaque ferme sur la période, dans l\'ordre demandé', function () {
    $rows = $this->service->compare($this->organisation->id, [$this->farmB->id, $this->farmA->id, $this->farmC->id], $this->period['date_from'], $this->period['date_to']);

    expect($rows->pluck('farm_name')->all())->toBe(['Ferme B', 'Ferme A', 'Ferme C']);

    $a = $rows[1];
    $b = $rows[0];
    $birdDaysA = (int) DailyRecord::query()->where('flock_id', $this->flockA->id)->whereDate('record_date', '>=', $this->period['date_from'])->sum('birds_present');
    $birdDaysB = (int) DailyRecord::query()->where('flock_id', $this->flockB->id)->sum('birds_present');

    expect($a['active_flocks'])->toBe(1)
        ->and($a['birds'])->toBe(930) // effectif recalculé après les 70 morts (dont 50 hors période)
        ->and($a['mortality'])->toBe(20)
        ->and($a['mortality_rate'])->toBe(round(20 / ($birdDaysA / 7) * 100, 2))
        ->and($a['avg_weight_g'])->toBe(1550.0)
        ->and($a['feed_kg'])->toBe(200.0)
        ->and($a['feed_per_bird_day_g'])->toBe(round(200000 / $birdDaysA, 1))
        ->and($a['laying_rate'])->toBe(0.0) // des relevés mais aucun œuf en chair
        ->and($a['sales'])->toBe(500000.0)
        ->and($a['expenses'])->toBe(100000.0)
        ->and($a['result'])->toBe(400000.0)
        ->and($a['sales_per_bird'])->toBe(round(500000 / 930));

    expect($b['eggs'])->toBe(820)
        ->and($b['laying_rate'])->toBe(round(820 / $birdDaysB * 100, 1))
        ->and($b['eggs_per_day'])->toBe(round(820 / 7, 1))
        ->and($b['mortality'])->toBe(5)
        ->and($b['sales'])->toBe(200000.0)
        ->and($b['expenses'])->toBe(0.0);

    expect($rows[2]['active_flocks'])->toBe(0)
        ->and($rows[2]['mortality_rate'])->toBeNull()
        ->and($rows[2]['sales'])->toBe(0.0);
});

it('restreint aux bandes du type de production demandé', function () {
    $rows = $this->service->compare($this->organisation->id, [$this->farmA->id, $this->farmB->id], $this->period['date_from'], $this->period['date_to'], ProductionKind::Broiler)->keyBy('farm_id');

    expect($rows[$this->farmA->id]['mortality'])->toBe(20)
        ->and($rows[$this->farmA->id]['active_flocks'])->toBe(1)
        ->and($rows[$this->farmB->id]['mortality'])->toBe(0)
        ->and($rows[$this->farmB->id]['eggs'])->toBe(0)
        ->and($rows[$this->farmB->id]['active_flocks'])->toBe(0)
        // Les ventes ne sont pas rattachées à un type : elles restent
        ->and($rows[$this->farmB->id]['sales'])->toBe(200000.0);
});

it('normalise les scores à 100 pour la meilleure ferme de chaque axe', function () {
    $rows = $this->service->compare($this->organisation->id, [$this->farmA->id, $this->farmB->id], $this->period['date_from'], $this->period['date_to']);
    $scores = $this->service->scores($rows);

    expect(array_keys($scores['axes']))->toBe(['survival', 'laying', 'growth', 'feed', 'sales'])
        ->and($scores['scores'][$this->farmA->id]['sales'])->toBe(100.0)
        ->and($scores['scores'][$this->farmB->id]['sales'])->toBe(40.0)
        ->and($scores['scores'][$this->farmB->id]['laying'])->toBe(100.0)
        ->and($scores['scores'][$this->farmA->id]['laying'])->toBe(0.0)
        ->and($scores['scores'][$this->farmA->id]['growth'])->toBe(100.0);

    // Sobriété aliment : la plus faible consommation par sujet vaut 100
    $byFeed = $rows->sortBy('feed_per_bird_day_g')->values();
    expect($scores['scores'][$byFeed[0]['farm_id']]['feed'])->toBe(100.0)
        ->and($scores['scores'][$byFeed[1]['farm_id']]['feed'])->toBeLessThan(100.0);

    // En chair, l'axe ponte disparaît
    expect(array_keys($this->service->scores($rows, ProductionKind::Broiler)['axes']))->not->toContain('laying');
});

it('propose au membre les fermes qu\'il peut comparer et exporte le tableau en Excel', function () {
    config(['queue.default' => 'sync']);
    actingAs($this->owner);
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($this->organisation);

    $page = app(FarmComparisonPage::class);
    $page->mount();

    expect($page->farmOptions())->toHaveCount(3)
        ->and($page->filters['farm_ids'])->toHaveCount(3)
        ->and($page->hasEnoughFarms())->toBeTrue();

    $page->filters = $this->period + ['farm_ids' => [$this->farmA->id, $this->farmB->id], 'production_kind' => null];
    expect($page->getRows())->toHaveCount(2)
        ->and($page->getLeaders()['sales'])->toBe($this->farmA->id)
        ->and($page->getLeaders()['laying_rate'])->toBe($this->farmB->id)
        ->and($page->buildXlsx())->not->toBeEmpty()
        ->and($page->exportExcel())->not->toBeNull();

    $page->filters['farm_ids'] = [$this->farmA->id];
    $page->updatedFilters();
    expect($page->hasEnoughFarms())->toBeFalse()
        ->and($page->getRows())->toBeEmpty();

    // Un employé affecté à deux fermes ne voit qu'elles
    actingAs($this->employee);
    $restricted = app(FarmComparisonPage::class);
    expect(array_keys($restricted->farmOptions()))->toBe([$this->farmA->id, $this->farmC->id]);
});

it('expose la comparaison au mobile en respectant les fermes accessibles, le plan et la validation', function () {
    $base = "/api/v1/organisations/{$this->organisation->slug}/compare";
    $query = http_build_query($this->period + ['farm_ids' => [$this->farmA->id, $this->farmB->id]]);

    Sanctum::actingAs($this->owner);
    $response = getJson("{$base}?{$query}")->assertOk();
    expect($response->json('farms'))->toHaveCount(2)
        ->and($response->json('farms.0.farm_name'))->toBe('Ferme A')
        ->and($response->json('farms.0.sales'))->toEqual(500000)
        ->and($response->json('farms.0.scores.sales'))->toEqual(100)
        ->and($response->json('axes'))->toHaveKey('survival');

    getJson("{$base}?".http_build_query($this->period + ['farm_ids' => [$this->farmA->id]]))->assertStatus(422);

    Sanctum::actingAs($this->employee);
    getJson("{$base}?{$query}")->assertForbidden();
    getJson("{$base}?".http_build_query($this->period + ['farm_ids' => [$this->farmA->id, $this->farmC->id]]))->assertOk();

    // Plan sans la fonctionnalité
    $this->organisation->plan->update(['features' => [PlanFeature::DailyRecords->value]]);
    Sanctum::actingAs($this->owner);
    getJson("{$base}?{$query}")->assertForbidden();
});
