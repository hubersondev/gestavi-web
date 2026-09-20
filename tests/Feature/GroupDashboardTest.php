<?php

use App\Filament\Farm\Pages\GroupDashboard;
use App\Models\Building;
use App\Models\DailyRecord;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\FarmDailyMetric;
use App\Models\Flock;
use App\Models\Organisation;
use App\Models\Sale;
use App\Models\User;
use App\Services\FarmMetricsAggregator;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * Consolidation groupe : agrégats quotidiens par ferme et tableau de bord de direction.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);
    $this->organisation = Organisation::factory()->create();
    $roles = $this->organisation->organisationRoles()->get()->keyBy('slug');

    $this->owner = User::factory()->create();
    $this->employee = User::factory()->create();
    $this->organisation->addMember($this->owner, $roles['owner']);
    $this->organisation->addMember($this->employee, $roles['employee']);

    $this->farmA = Farm::factory()->create(['organisation_id' => $this->organisation->id, 'name' => 'Ferme Anyama']);
    $this->farmB = Farm::factory()->create(['organisation_id' => $this->organisation->id, 'name' => 'Ferme Béoumi']);

    $buildingA = Building::factory()->create(['farm_id' => $this->farmA->id]);
    $this->flockA = Flock::factory()->active()->create(['building_id' => $buildingA->id, 'quantity_started' => 1000, 'quantity_current' => 1000, 'start_date' => now()->subDays(10)]);
});

it('agrège production, ventes et dépenses par ferme et par jour', function () {
    DailyRecord::create(['flock_id' => $this->flockA->id, 'record_date' => now()->subDay()->toDateString(), 'mortality' => 10, 'eggs_collected' => 800, 'feed_consumed_kg' => 120]);
    DailyRecord::create(['flock_id' => $this->flockA->id, 'record_date' => now()->toDateString(), 'mortality' => 5, 'eggs_collected' => 790, 'feed_consumed_kg' => 118]);

    Sale::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farmA->id, 'sale_date' => now()->toDateString(), 'total' => 250000, 'status' => 'pending']);
    Expense::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farmA->id, 'expense_date' => now()->toDateString(), 'amount' => 40000]);
    Expense::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farmB->id, 'expense_date' => now()->toDateString(), 'amount' => 15000]);

    $written = app(FarmMetricsAggregator::class)->aggregate(now()->subDay(), now(), $this->organisation->id);

    expect($written)->toBe(4);

    $today = FarmDailyMetric::query()->where('farm_id', $this->farmA->id)->whereDate('metric_date', now()->toDateString())->first();
    $yesterday = FarmDailyMetric::query()->where('farm_id', $this->farmA->id)->whereDate('metric_date', now()->subDay()->toDateString())->first();
    $farmB = FarmDailyMetric::query()->where('farm_id', $this->farmB->id)->whereDate('metric_date', now()->toDateString())->first();

    expect($yesterday->mortality)->toBe(10)
        ->and($yesterday->birds_present)->toBe(990)
        ->and($today->mortality)->toBe(5)
        ->and($today->birds_present)->toBe(985)
        ->and($today->eggs_collected)->toBe(790)
        ->and((float) $today->feed_kg)->toBe(118.0)
        ->and((float) $today->sales_total)->toBe(250000.0)
        ->and((float) $today->expenses_total)->toBe(40000.0)
        ->and($today->active_flocks)->toBe(1)
        ->and((float) $farmB->expenses_total)->toBe(15000.0)
        ->and($farmB->active_flocks)->toBe(0);

    // Idempotence : un second passage ne duplique rien
    app(FarmMetricsAggregator::class)->aggregate(now()->subDay(), now(), $this->organisation->id);
    expect(FarmDailyMetric::query()->where('organisation_id', $this->organisation->id)->count())->toBe(4);
});

it('la commande planifiée calcule les agrégats', function () {
    $this->artisan('gestavi:aggregate-farm-metrics', ['--from' => now()->subDays(2)->toDateString(), '--to' => now()->toDateString()])
        ->assertSuccessful();

    expect(FarmDailyMetric::query()->where('organisation_id', $this->organisation->id)->count())->toBe(6);
});

it('le tableau de bord groupe est réservé aux rôles à accès global et compare les fermes', function () {
    DailyRecord::create(['flock_id' => $this->flockA->id, 'record_date' => now()->toDateString(), 'mortality' => 5, 'eggs_collected' => 700]);
    app(FarmMetricsAggregator::class)->aggregate(now(), now(), $this->organisation->id);

    Filament::setCurrentPanel(Filament::getPanel('farm'));

    actingAs($this->employee);
    Filament::setTenant($this->organisation);
    expect(GroupDashboard::canAccess())->toBeFalse();

    actingAs($this->owner);
    Filament::setTenant($this->organisation);
    expect(GroupDashboard::canAccess())->toBeTrue();

    Livewire::test(GroupDashboard::class)
        ->assertOk()
        ->assertSee('Ferme Anyama')
        ->assertSee('Ferme Béoumi')
        ->assertSee('Comparaison des fermes');
});
