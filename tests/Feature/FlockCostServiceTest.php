<?php

use App\Enums\ExpenseCategory;
use App\Enums\PayrollStatus;
use App\Enums\StockCategoryType;
use App\Enums\StockMovementReason;
use App\Models\Building;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\Organisation;
use App\Models\Payroll;
use App\Models\Sale;
use App\Models\Stock;
use App\Models\StockCategory;
use App\Services\FlockCostService;

/**
 * Coût de revient par bande : coûts directs, répartition des charges de ferme, marge.
 */
beforeEach(function () {
    $this->organisation = Organisation::factory()->create();
    $this->farm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);
    $this->building = Building::factory()->create(['farm_id' => $this->farm->id]);

    $this->flock = Flock::factory()->active()->create([
        'building_id' => $this->building->id,
        'quantity_started' => 1000,
        'quantity_current' => 1000,
        'start_date' => now()->subDays(29),
        'purchase_price_per_unit' => 500,
        'total_purchase_price' => 500000,
    ]);
});

function makeCategoryStock(Organisation $organisation, Farm $farm, StockCategoryType $type, float $unitPrice): Stock
{
    $category = StockCategory::create([
        'organisation_id' => $organisation->id,
        'name' => $type->value.' '.uniqid(),
        'slug' => $type->value.'-'.uniqid(),
        'type' => $type,
    ]);

    return Stock::factory()->create([
        'organisation_id' => $organisation->id,
        'farm_id' => $farm->id,
        'stock_category_id' => $category->id,
        'quantity' => 10000,
        'unit_price' => $unitPrice,
    ]);
}

it('additionne poussins, aliment, santé, dépenses directes et calcule la marge', function () {
    $feed = makeCategoryStock($this->organisation, $this->farm, StockCategoryType::Feed, 400);
    $feed->recordOut(1500, StockMovementReason::Consumption, flockId: $this->flock->id);   // 600 000

    $medicine = makeCategoryStock($this->organisation, $this->farm, StockCategoryType::Medicine, 2500);
    $medicine->recordOut(10, StockMovementReason::Consumption, flockId: $this->flock->id); // 25 000

    Expense::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'flock_id' => $this->flock->id, 'category' => ExpenseCategory::Veterinary, 'amount' => 30000, 'expense_date' => now()->subDays(10)]);
    Expense::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'flock_id' => $this->flock->id, 'category' => ExpenseCategory::Transport, 'amount' => 45000, 'expense_date' => now()->subDays(5)]);

    $sale = Sale::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'sale_date' => now()->toDateString()]);
    $sale->items()->create(['product_name' => 'Poulets vifs', 'flock_id' => $this->flock->id, 'is_live_birds' => true, 'quantity' => 400, 'unit_price' => 3500]); // 1 400 000

    $breakdown = app(FlockCostService::class)->breakdown($this->flock->fresh());

    expect($breakdown['couts']['poussins'])->toBe(500000.0)
        ->and($breakdown['couts']['aliment'])->toBe(600000.0)
        ->and($breakdown['couts']['sante'])->toBe(55000.0)
        ->and($breakdown['couts']['autres_directs'])->toBe(45000.0)
        ->and($breakdown['couts']['total'])->toBe(1200000.0)
        ->and($breakdown['produits']['ventes'])->toBe(1400000.0)
        ->and($breakdown['marge']['brute'])->toBe(200000.0)
        ->and($breakdown['marge']['taux'])->toBe(14.29)
        ->and($breakdown['unitaires']['cout_par_sujet_mis_en_place'])->toBe(1200.0)
        ->and($breakdown['cle_repartition'])->toBe(100.0);
});

it('répartit la paie et les charges de ferme au prorata des sujets-jours entre les bandes', function () {
    // Deuxième bande, même ferme, même période, effectif moitié → clé 2/3 vs 1/3
    Flock::factory()->active()->create([
        'building_id' => $this->building->id,
        'quantity_started' => 500,
        'quantity_current' => 500,
        'start_date' => now()->subDays(29),
    ]);

    $employee = Employee::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id]);
    Payroll::create([
        'employee_id' => $employee->id,
        'period_year' => now()->year,
        'period_month' => now()->month,
        'gross_salary' => 300000,
        'cnps_employer' => 28525,
        'status' => PayrollStatus::Validated,
    ]);

    // Charge de ferme non imputée (électricité) sur la période
    Expense::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'flock_id' => null, 'category' => ExpenseCategory::Utilities, 'amount' => 90000, 'expense_date' => now()->subDays(3)]);

    $breakdown = app(FlockCostService::class)->breakdown($this->flock->fresh());

    // 1 000 / (1 000 + 500) = 66,67 %
    expect($breakdown['cle_repartition'])->toBe(66.67)
        ->and($breakdown['couts']['main_oeuvre_repartie'])->toBe(round(328525 * (1000 / 1500), 2))
        ->and($breakdown['couts']['charges_ferme_reparties'])->toBe(round(90000 * (1000 / 1500), 2));
});

it('ne divise jamais par zéro et ignore les dépenses annulées', function () {
    Expense::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'flock_id' => $this->flock->id, 'category' => ExpenseCategory::Feed, 'amount' => 100000, 'status' => 'cancelled', 'expense_date' => now()]);

    $breakdown = app(FlockCostService::class)->breakdown($this->flock->fresh());

    expect($breakdown['couts']['aliment'])->toBe(0.0)
        ->and($breakdown['marge']['taux'])->toBeNull()
        ->and($breakdown['unitaires']['cout_par_kg_vif'])->toBeNull()
        ->and($breakdown['unitaires']['cout_par_oeuf'])->toBeNull();
});

it('la page « Coût de revient » s\'affiche pour un propriétaire avec la consolidation par ferme', function () {
    $this->seed(\Database\Seeders\OrganisationPermissionSeeder::class);
    $organisation = \App\Models\Organisation::factory()->create();
    $owner = \App\Models\User::factory()->create();
    $organisation->addMember($owner, $organisation->organisationRoles()->where('slug', 'owner')->first());

    $farm = \App\Models\Farm::factory()->create(['organisation_id' => $organisation->id]);
    $building = \App\Models\Building::factory()->create(['farm_id' => $farm->id]);
    \App\Models\Flock::factory()->active()->create(['building_id' => $building->id, 'quantity_started' => 200, 'quantity_current' => 200, 'start_date' => now()->subDays(10), 'total_purchase_price' => 100000]);

    \Pest\Laravel\actingAs($owner);
    \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('farm'));
    \Filament\Facades\Filament::setTenant($organisation);

    expect(\App\Filament\Farm\Pages\Reports\FlockCostReportPage::canAccess())->toBeTrue();

    \Livewire\Livewire::test(\App\Filament\Farm\Pages\Reports\FlockCostReportPage::class)
        ->assertOk()
        ->assertSee('Consolidation par ferme')
        ->assertSee($farm->name);
});
