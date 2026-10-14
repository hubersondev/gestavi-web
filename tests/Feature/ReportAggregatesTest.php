<?php

use App\Enums\PlanFeature;
use App\Enums\SaleStatus;
use App\Enums\StockMovementReason;
use App\Enums\TransactionCategory;
use App\Filament\Farm\Pages\Reports\CommercialReportPage;
use App\Filament\Farm\Pages\Reports\FinancialReportPage;
use App\Filament\Farm\Pages\Reports\ProductionReportPage;
use App\Filament\Farm\Pages\Reports\StockReportPage;
use App\Models\Account;
use App\Models\Building;
use App\Models\DailyRecord;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\Sale;
use App\Models\Stock;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;

/**
 * KPI des rapports calculés en SQL sur toute la période (aucune troncature),
 * filtre ferme et comparaison inter-fermes.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);

    $plan = Plan::factory()->create(['features' => collect(PlanFeature::cases())->map->value->all()]);
    $this->organisation = Organisation::factory()->create(['plan_id' => $plan->id]);
    $ownerRole = $this->organisation->organisationRoles()->where('slug', 'owner')->first();

    $this->user = User::factory()->create();
    $this->organisation->addMember($this->user, $ownerRole);

    $this->farmA = Farm::factory()->create(['organisation_id' => $this->organisation->id, 'name' => 'Ferme A']);
    $this->farmB = Farm::factory()->create(['organisation_id' => $this->organisation->id, 'name' => 'Ferme B']);

    actingAs($this->user);
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($this->organisation);

    $this->period = ['date_from' => now()->subDays(10)->toDateString(), 'date_to' => now()->toDateString()];
});

function reportPage(string $class, array $filters): object
{
    $page = app($class);
    $page->filters = $filters;

    return $page;
}

it('calcule les KPI financiers sur 250 transactions sans troncature, avec soldes d\'ouverture et de clôture', function () {
    $accountA = Account::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farmA->id, 'initial_balance' => 1000, 'current_balance' => 1000]);
    $accountB = Account::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farmB->id, 'initial_balance' => 500, 'current_balance' => 500]);

    // Antérieur à la période : +200 sur A
    // Les soldes sont hors assignation de masse : on passe par le compte (verrou + solde calculé)
    $accountA->credit(200, 'avant', TransactionCategory::Other, date: now()->subDays(20)->toDateString());

    // 250 transactions dans la période : 200 crédits de 10 sur A, 50 débits de 4 sur B
    $rows = [];
    for ($i = 0; $i < 200; $i++) {
        $rows[] = ['organisation_id' => $accountA->organisation_id, 'farm_id' => $accountA->farm_id, 'account_id' => $accountA->id, 'type' => 'credit', 'category' => 'other', 'amount' => 10, 'balance_before' => 0, 'balance_after' => 0, 'description' => 'c', 'transaction_date' => now()->subDays($i % 10)->toDateString(), 'created_at' => now(), 'updated_at' => now()];
    }
    for ($i = 0; $i < 50; $i++) {
        $rows[] = ['organisation_id' => $accountB->organisation_id, 'farm_id' => $accountB->farm_id, 'account_id' => $accountB->id, 'type' => 'debit', 'category' => 'other', 'amount' => 4, 'balance_before' => 0, 'balance_after' => 0, 'description' => 'd', 'transaction_date' => now()->subDays($i % 10)->toDateString(), 'created_at' => now(), 'updated_at' => now()];
    }
    Transaction::query()->insert($rows);

    $kpis = reportPage(FinancialReportPage::class, $this->period + ['farm_id' => null, 'account_id' => null])->getKpis();

    expect($kpis['count'])->toBe(250)
        ->and($kpis['credits'])->toBe(2000.0)
        ->and($kpis['debits'])->toBe(200.0)
        ->and($kpis['net'])->toBe(1800.0)
        ->and($kpis['opening_balance'])->toBe(1700.0)   // 1 000 + 200 (A) + 500 (B)
        ->and($kpis['closing_balance'])->toBe(3500.0);  // 1 700 + 2 000 − 200

    $comparison = reportPage(FinancialReportPage::class, $this->period)->getFarmComparison();

    expect($comparison)->toHaveCount(2)
        ->and($comparison->firstWhere('farm_name', 'Ferme A')->credits)->toBe(2000.0)
        ->and($comparison->firstWhere('farm_name', 'Ferme B')->debits)->toBe(200.0);

    $onlyB = reportPage(FinancialReportPage::class, $this->period + ['farm_id' => $this->farmB->id])->getKpis();

    expect($onlyB['count'])->toBe(50)
        ->and($onlyB['opening_balance'])->toBe(500.0)
        ->and($onlyB['closing_balance'])->toBe(300.0);
});

it('calcule les KPI de production (mortalité, ponte, aliment) et la comparaison inter-fermes', function () {
    $buildingA = Building::factory()->create(['farm_id' => $this->farmA->id]);
    $buildingB = Building::factory()->create(['farm_id' => $this->farmB->id]);

    $flockA = Flock::factory()->active()->create(['building_id' => $buildingA->id, 'quantity_started' => 1000, 'quantity_current' => 1000, 'start_date' => now()->subDays(30)]);
    $flockB = Flock::factory()->active()->create(['building_id' => $buildingB->id, 'quantity_started' => 500, 'quantity_current' => 500, 'start_date' => now()->subDays(30)]);

    // Ferme A : J-2 → 20 morts, 800 œufs ; J-1 → 0 mort, 784 œufs
    DailyRecord::create(['flock_id' => $flockA->id, 'record_date' => now()->subDays(2)->toDateString(), 'mortality' => 20, 'eggs_collected' => 800, 'feed_consumed_kg' => 100]);
    DailyRecord::create(['flock_id' => $flockA->id, 'record_date' => now()->subDay()->toDateString(), 'mortality' => 0, 'eggs_collected' => 784, 'feed_consumed_kg' => 98]);
    // Ferme B : J-1 → 5 morts, 5 réformes, 392 œufs
    DailyRecord::create(['flock_id' => $flockB->id, 'record_date' => now()->subDay()->toDateString(), 'mortality' => 5, 'culls' => 5, 'eggs_collected' => 392, 'feed_consumed_kg' => 49]);
    // Hors période : ignoré
    DailyRecord::create(['flock_id' => $flockB->id, 'record_date' => now()->subDays(15)->toDateString(), 'mortality' => 100]);

    $page = reportPage(ProductionReportPage::class, $this->period + ['farm_id' => null, 'flock_id' => null]);
    $kpis = $page->getKpis();

    // Poules-jours : A = 980 + 980 ; B = 390 → 2 350 ; œufs = 1 976 → 84,09 %
    // Effectif début de période : A = 980 + 20 = 1 000 ; B = 390 + 5 + 5 = 400 (100 morts hors période) → 1 400 ; morts 25 → 1,79 %
    expect($kpis['days'])->toBe(3)
        ->and($kpis['flocks'])->toBe(2)
        ->and($kpis['mortality'])->toBe(25)
        ->and($kpis['culls'])->toBe(5)
        ->and($kpis['starting_birds'])->toBe(1400)
        ->and($kpis['mortality_rate'])->toBe(1.79)
        ->and($kpis['eggs'])->toBe(1976)
        ->and($kpis['laying_rate'])->toBe(84.09)
        ->and($kpis['total_feed'])->toBe(247.0)
        ->and($kpis['feed_per_bird_day'])->toBe(105.1)
        ->and($kpis['ic'])->toBeNull();

    $comparison = $page->getFarmComparison();

    expect($comparison)->toHaveCount(2)
        ->and($comparison->firstWhere('farm_name', 'Ferme A')->eggs)->toBe(1584)
        ->and($comparison->firstWhere('farm_name', 'Ferme A')->laying_rate)->toBe(80.82)
        ->and($comparison->firstWhere('farm_name', 'Ferme B')->culls)->toBe(5);

    // Filtre sur la ferme B seule
    $pageB = reportPage(ProductionReportPage::class, $this->period + ['farm_id' => $this->farmB->id, 'flock_id' => null]);

    expect($pageB->getKpis()['mortality'])->toBe(5)
        ->and($pageB->getFarmComparison())->toHaveCount(1);

    // Filtre sur une bande : les indicateurs de bande (IC/GMQ) sont renseignés quand des pesées existent
    DailyRecord::create(['flock_id' => $flockA->id, 'record_date' => now()->toDateString(), 'age_days' => 30, 'average_weight_g' => 1800]);
    $pageFlock = reportPage(ProductionReportPage::class, $this->period + ['farm_id' => null, 'flock_id' => $flockA->id]);

    expect($pageFlock->getKpis()['flocks'])->toBe(1)
        ->and($pageFlock->getKpis()['gmq'])->not->toBeNull();
});

it('calcule les KPI commerciaux et les compare par ferme', function () {
    foreach (range(1, 3) as $i) {
        Sale::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farmA->id, 'sale_date' => now()->subDay(), 'status' => SaleStatus::Pending, 'subtotal' => 10000, 'tax_amount' => 1800, 'total' => 11800, 'paid' => 0, 'balance' => 11800, 'due_date' => now()->subDays(2)]);
    }
    Sale::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farmB->id, 'sale_date' => now()->subDay(), 'status' => SaleStatus::Paid, 'subtotal' => 5000, 'tax_amount' => 0, 'total' => 5000, 'paid' => 5000, 'balance' => 0]);
    Sale::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farmB->id, 'sale_date' => now()->subDay(), 'status' => SaleStatus::Cancelled, 'total' => 99999, 'balance' => 99999]);

    $kpis = reportPage(CommercialReportPage::class, $this->period + ['farm_id' => null])->getKpis();

    expect($kpis['count'])->toBe(4)
        ->and($kpis['total_ca'])->toBe(40400.0)
        ->and($kpis['ca_ht'])->toBe(35000.0)
        ->and($kpis['total_paid'])->toBe(5000.0)
        ->and($kpis['total_due'])->toBe(35400.0)
        ->and($kpis['avg_basket'])->toBe(10100.0)
        ->and($kpis['overdue'])->toBe(3);

    $comparison = reportPage(CommercialReportPage::class, $this->period)->getFarmComparison();

    expect($comparison)->toHaveCount(2)
        ->and($comparison->firstWhere('farm_name', 'Ferme A')->total_ca)->toBe(35400.0)
        ->and($comparison->firstWhere('farm_name', 'Ferme B')->total_paid)->toBe(5000.0);
});

it('calcule les KPI stocks (valeur, seuil, mouvements) et la comparaison par ferme', function () {
    $stockA = Stock::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farmA->id, 'quantity' => 100, 'min_quantity' => 10, 'unit_price' => 50]);
    $stockB = Stock::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farmB->id, 'quantity' => 5, 'min_quantity' => 20, 'unit_price' => 100]);

    $stockA->recordIn(40, StockMovementReason::Purchase);
    $stockA->recordOut(15, StockMovementReason::Consumption);
    $stockB->recordIn(10, StockMovementReason::Purchase);

    $kpis = reportPage(StockReportPage::class, $this->period + ['farm_id' => null, 'category_id' => null])->getKpis();

    // Après mouvements : A = 125 × 50 = 6 250 ; B = 15 × 100 = 1 500 (sous seuil 20)
    expect($kpis['products'])->toBe(2)
        ->and($kpis['total_value'])->toBe(7750.0)
        ->and($kpis['low_stock'])->toBe(1)
        ->and($kpis['entries'])->toBe(50.0)
        ->and($kpis['exits'])->toBe(15.0)
        ->and($kpis['movement_count'])->toBe(3);

    $comparison = reportPage(StockReportPage::class, $this->period)->getFarmComparison();

    expect($comparison)->toHaveCount(2)
        ->and($comparison->firstWhere('farm_name', 'Ferme A')->exits)->toBe(15.0)
        ->and($comparison->firstWhere('farm_name', 'Ferme B')->low_stock)->toBe(1);
});

it('le solde d\'ouverture du rapport financier ne recompte pas le solde initial porté par la transaction d\'ouverture', function () {
    app(\App\Services\AccountService::class)->createAccount([
        'organisation_id' => $this->organisation->id,
        'farm_id' => $this->farmA->id,
        'name' => 'Banque reprise',
        'type' => \App\Enums\AccountType::Bank,
        'initial_balance' => 100000,
        'initial_balance_date' => now()->subDays(30)->toDateString(),
    ]);

    $kpis = reportPage(FinancialReportPage::class, $this->period + ['farm_id' => null, 'account_id' => null])->getKpis();

    expect($kpis['opening_balance'])->toBe(100000.0)
        ->and($kpis['closing_balance'])->toBe(100000.0);
});
