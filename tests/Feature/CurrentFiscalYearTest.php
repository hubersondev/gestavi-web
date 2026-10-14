<?php

use App\Enums\FiscalYearStatus;
use App\Enums\PlanFeature;
use App\Enums\SaleStatus;
use App\Filament\Farm\Components\FiscalYearDatePicker;
use App\Filament\Farm\Pages\GroupDashboard;
use App\Filament\Farm\Resources\Sales\Pages\ListSales;
use App\Livewire\FiscalYearSelector;
use App\Models\Farm;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\Sale;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\CurrentFiscalYear;
use App\Services\SaleService;
use Database\Seeders\ChartAccountSeeder;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * Exercice actif : filtre d'affichage des journaux datés, défaut des dates et des périodes de rapport,
 * sans effet sur les services.
 */
beforeEach(function () {
    $this->seed(ChartAccountSeeder::class);
    $this->seed(OrganisationPermissionSeeder::class);
    $plan = Plan::factory()->create(['features' => collect(PlanFeature::cases())->map->value->all()]);
    $this->organisation = Organisation::factory()->create(['plan_id' => $plan->id]);
    $this->farm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);
    $this->owner = User::factory()->create();
    $this->organisation->addMember($this->owner, 'owner');

    $lastYear = now()->subYear()->year;
    $this->previous = FiscalYear::create(['organisation_id' => $this->organisation->id, 'label' => (string) $lastYear, 'starts_on' => "{$lastYear}-01-01", 'ends_on' => "{$lastYear}-12-31", 'status' => FiscalYearStatus::Open]);
    $this->current = FiscalYear::create(['organisation_id' => $this->organisation->id, 'label' => (string) now()->year, 'starts_on' => now()->startOfYear()->toDateString(), 'ends_on' => now()->endOfYear()->toDateString(), 'status' => FiscalYearStatus::Open]);

    $this->saleLastYear = app(SaleService::class)->createSale(
        ['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'sale_date' => "{$lastYear}-05-10", 'status' => SaleStatus::Pending, 'tax_percent' => 0],
        [['product_name' => 'Œufs', 'quantity' => 10, 'unit_price' => 100]],
    );
    $this->saleThisYear = app(SaleService::class)->createSale(
        ['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'sale_date' => now()->toDateString(), 'status' => SaleStatus::Pending, 'tax_percent' => 0],
        [['product_name' => 'Œufs', 'quantity' => 10, 'unit_price' => 100]],
    );

    actingAs($this->owner);
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($this->organisation);
});

it('sélectionne par défaut l\'exercice qui couvre aujourd\'hui, mémorise un choix explicite et accepte « tous »', function () {
    $service = app(CurrentFiscalYear::class);

    expect($service->get()?->id)->toBe($this->current->id)
        ->and($service->defaultDate()->toDateString())->toBe(today()->toDateString())
        ->and($service->reportPeriod())->toBe(['date_from' => now()->startOfYear()->toDateString(), 'date_to' => now()->toDateString()]);

    $service->set($this->previous->id);

    expect($service->get()?->id)->toBe($this->previous->id)
        ->and($service->defaultDate()->toDateString())->toBe($this->previous->ends_on->toDateString())
        ->and($service->reportPeriod())->toBe(['date_from' => $this->previous->starts_on->toDateString(), 'date_to' => $this->previous->ends_on->toDateString()])
        ->and($service->dateWarning(now()->toDateString()))->toContain('hors de l\'exercice actif')
        ->and($service->dateWarning($this->previous->starts_on))->toBeNull()
        ->and($service->notice())->toContain('Exercice « '.$this->previous->label.' »');

    $service->set(null);

    expect($service->get())->toBeNull()
        ->and($service->notice())->toBeNull()
        ->and($service->reportPeriod())->toBe(['date_from' => now()->startOfMonth()->toDateString(), 'date_to' => now()->toDateString()]);
});

it('filtre la liste des factures sur l\'exercice actif et affiche toutes les factures sur « tous les exercices »', function () {
    Livewire::test(ListSales::class)
        ->assertCanSeeTableRecords([$this->saleThisYear])
        ->assertCanNotSeeTableRecords([$this->saleLastYear])
        ->assertSee('Exercice « '.$this->current->label.' »');

    app(CurrentFiscalYear::class)->set($this->previous->id);

    Livewire::test(ListSales::class)
        ->assertCanSeeTableRecords([$this->saleLastYear])
        ->assertCanNotSeeTableRecords([$this->saleThisYear]);

    app(CurrentFiscalYear::class)->set(null);

    Livewire::test(ListSales::class)
        ->assertCanSeeTableRecords([$this->saleLastYear, $this->saleThisYear])
        ->assertDontSee('seules les pièces datées');
});

it('le sélecteur de la barre du haut change l\'exercice actif et renvoie vers la page d\'origine', function () {
    Livewire::test(FiscalYearSelector::class)
        ->assertSee('Exercice '.$this->current->label)
        ->assertSee('Tous les exercices')
        ->assertSet('selectedFiscalYearId', $this->current->id)
        ->call('selectFiscalYear', (string) $this->previous->id)
        ->assertRedirect();

    expect(app(CurrentFiscalYear::class)->get()?->id)->toBe($this->previous->id);
});

it('les rapports et les formulaires prennent l\'exercice actif pour défaut', function () {
    app(CurrentFiscalYear::class)->set($this->previous->id);

    $dashboard = Livewire::test(GroupDashboard::class)->instance();

    expect($dashboard->filters['date_from'])->toBe($this->previous->starts_on->toDateString())
        ->and($dashboard->filters['date_to'])->toBe($this->previous->ends_on->toDateString());

    $picker = FiscalYearDatePicker::make('sale_date');

    expect($picker->getDefaultState())->toBe($this->previous->ends_on->toDateString());
});

it('n\'affecte ni les services ni les ventes datées hors exercice actif', function () {
    app(CurrentFiscalYear::class)->set($this->previous->id);

    // Une pièce datée hors exercice actif est acceptée
    $sale = app(SaleService::class)->createSale(
        ['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'sale_date' => now()->toDateString(), 'status' => SaleStatus::Pending, 'tax_percent' => 0],
        [['product_name' => 'Œufs', 'quantity' => 1, 'unit_price' => 100]],
    );
    expect($sale->exists)->toBeTrue()
        ->and(Sale::query()->where('organisation_id', $this->organisation->id)->count())->toBe(3);

    // La clôture de l'exercice courant voit bien ses écritures alors que l'exercice actif est l'autre
    $closed = app(AccountingService::class)->closeFiscalYear($this->current, $this->owner->id);

    expect((float) $closed->net_result)->toBe(1100.0);
});
