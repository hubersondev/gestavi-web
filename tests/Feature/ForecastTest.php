<?php

use App\Enums\PlanFeature;
use App\Enums\PredictionType;
use App\Enums\ProductionKind;
use App\Exceptions\ForecastException;
use App\Filament\Farm\Pages\Forecasts\ForecastsPage;
use App\Models\BreedStandard;
use App\Models\Building;
use App\Models\ChickenType;
use App\Models\DailyRecord;
use App\Models\Farm;
use App\Models\FarmLog;
use App\Models\Flock;
use App\Models\FlockPrediction;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\OrganisationAlertNotification;
use App\Services\Forecast\ForecastService;
use Database\Seeders\BreedStandardSeeder;
use Database\Seeders\ChickenTypeSeeder;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/**
 * Prévisions : courbes de souche interpolées, prévisions composites à J+7 par grandeur, valeurs
 * réelles et précision, alerte aux responsables sur les écarts, page du panel.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);
    $this->seed(ChickenTypeSeeder::class);
    $this->seed(BreedStandardSeeder::class);
    Notification::fake();

    $plan = Plan::factory()->create(['features' => collect(PlanFeature::cases())->map->value->all()]);
    $this->organisation = Organisation::factory()->create(['plan_id' => $plan->id]);
    $roles = $this->organisation->organisationRoles()->get()->keyBy('slug');
    $this->owner = User::factory()->create();
    $this->organisation->addMember($this->owner, $roles['owner']);
    $this->farm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);
    $this->building = Building::factory()->create(['farm_id' => $this->farm->id]);
    $this->service = app(ForecastService::class);
});

/**
 * Bande de pondeuses ISA Brown de 1 000 sujets, âgée de $ageDays, avec $days relevés quotidiens
 * récents (ponte à $layingRate %, 1 mort par jour, 110 g d'aliment par sujet).
 */
function layerFlock(int $ageDays = 175, int $days = 20, float $layingRate = 90): Flock
{
    $layer = ChickenType::query()->where('production_kind', ProductionKind::Layer)->firstOrFail();
    $flock = Flock::factory()->active()->create([
        'building_id' => test()->building->id,
        'chicken_type_id' => $layer->id,
        'breed' => 'ISA Brown',
        'quantity_started' => 1000,
        'quantity_current' => 1000,
        'start_date' => now()->subDays($ageDays),
        'age_at_arrival' => 0,
    ]);

    for ($i = $days; $i >= 1; $i--) {
        DailyRecord::create([
            'organisation_id' => $flock->organisation_id,
            'farm_id' => $flock->farm_id,
            'flock_id' => $flock->id,
            'record_date' => now()->subDays($i)->toDateString(),
            'mortality' => 1,
            'culls' => 0,
            'eggs_collected' => (int) round(($flock->fresh()->quantity_current) * $layingRate / 100),
            'feed_consumed_kg' => round($flock->fresh()->quantity_current * 0.110, 2),
        ]);
    }

    return $flock->fresh();
}

it('interpole la courbe de la souche entre deux âges connus', function () {
    expect(BreedStandard::expectedValue('ISA Brown', ProductionKind::Layer, 'expected_laying_rate', 168))->toBe(95.0)
        ->and(BreedStandard::expectedValue('isa-brown', ProductionKind::Layer, 'expected_laying_rate', 210))->toBe(94.0)
        ->and(BreedStandard::expectedValue('ISA Brown', ProductionKind::Layer, 'expected_laying_rate', 1000))->toBe(75.0)
        ->and(BreedStandard::expectedValue('Cobb 500', ProductionKind::Broiler, 'expected_weight_g', 24))->toBeGreaterThan(910.0)->toBeLessThan(1450.0)
        ->and(BreedStandard::expectedValue('Inconnue', ProductionKind::Layer, 'expected_laying_rate', 100))->toBeNull()
        ->and(BreedStandard::breeds())->toContain('ISA Brown', 'Cobb 500');
});

it('prévoit à J+7 la mortalité, la ponte et l\'aliment d\'une bande de pondeuses, avec confiance et facteurs', function () {
    $flock = layerFlock();

    $result = $this->service->forecastFlock($flock);

    expect($result['created'])->toBe(21)
        ->and($result['anomalies'])->toBe([]);

    $laying = FlockPrediction::query()->where('flock_id', $flock->id)->forType(PredictionType::LayingRate)->orderByDesc('target_date')->first();

    expect($laying->target_date->toDateString())->toBe(now()->addDays(7)->toDateString())
        ->and((float) $laying->predicted_value)->toBeGreaterThan(80.0)->toBeLessThan(100.0)
        ->and((float) $laying->confidence)->toBeGreaterThanOrEqual(20.0)->toBeLessThanOrEqual(95.0)
        ->and((float) $laying->standard_value)->toBeGreaterThan(90.0)
        ->and($laying->target_age_days)->toBe(182)
        ->and(collect($laying->factors)->pluck('key')->all())->toBe(['regression', 'trend', 'standard', 'age']);

    $mortality = FlockPrediction::query()->where('flock_id', $flock->id)->forType(PredictionType::Mortality)->orderByDesc('target_date')->first();
    expect((float) $mortality->predicted_value)->toBeGreaterThan(2.0, json_encode($mortality->factors))->toBeLessThan(5.0);

    $feed = FlockPrediction::query()->where('flock_id', $flock->id)->forType(PredictionType::FeedPerBird)->orderByDesc('target_date')->first();
    expect((float) $feed->predicted_value)->toBeGreaterThan(90.0)->toBeLessThan(130.0);

    // Pas de poids pour des pondeuses sans pesée, et rejeu sans doublon
    expect(FlockPrediction::query()->where('flock_id', $flock->id)->forType(PredictionType::Weight)->count())->toBe(0)
        ->and($this->service->forecastFlock($flock)['created'])->toBe(0);
});

it('refuse une bande inactive ou sans assez de relevés', function () {
    $flock = layerFlock(days: 3);

    expect(fn () => $this->service->forecastFlock($flock))->toThrow(ForecastException::class, 'au moins 7 relevés');

    $flock->update(['status' => 'completed']);
    expect(fn () => $this->service->forecastFlock($flock->fresh()))->toThrow(ForecastException::class, 'pas active');
});

it('renseigne la valeur réelle et la précision des prévisions échues', function () {
    $flock = layerFlock();
    FlockPrediction::query()->create([
        'organisation_id' => $flock->organisation_id,
        'farm_id' => $flock->farm_id,
        'flock_id' => $flock->id,
        'type' => PredictionType::LayingRate,
        'prediction_date' => now()->subDays(8)->toDateString(),
        'target_date' => now()->subDay()->toDateString(),
        'predicted_value' => 85,
        'confidence' => 70,
    ]);

    expect($this->service->recordActuals($flock))->toBe(1);

    $prediction = FlockPrediction::query()->where('flock_id', $flock->id)->sole();
    $actual = (float) $prediction->actual_value;

    expect($actual)->toBeGreaterThan(85.0)->toBeLessThan(95.0)
        ->and((float) $prediction->variance)->toEqual(round($actual - 85, 2))
        ->and($prediction->accuracyLevel())->toBe('good');
});

it('alerte les responsables et journalise quand la ponte prévue décroche de la souche', function () {
    $flock = layerFlock(layingRate: 55);

    $this->artisan('gestavi:forecast')->assertSuccessful();

    Notification::assertSentTo($this->owner, OrganisationAlertNotification::class, fn (OrganisationAlertNotification $n): bool => $n->alertKey === 'forecast_degradation'
        && str_contains($n->lines[0], $flock->code)
        && str_contains($n->lines[0], 'Taux de ponte prévu'));

    expect(FarmLog::query()->where('flock_id', $flock->id)->where('title', 'like', 'Prévision défavorable%')->count())->toBe(1)
        ->and(FlockPrediction::query()->where('flock_id', $flock->id)->count())->toBe(21);
});

it('la page Prévisions affiche les valeurs à J+7 de la bande choisie', function () {
    $flock = layerFlock();
    $this->service->forecastFlock($flock);

    $this->actingAs($this->owner);
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($this->organisation);

    Livewire::test(ForecastsPage::class)
        ->assertSet('filters.flock_id', $flock->id)
        ->assertSet('filters.type', 'laying_rate')
        ->assertSee('Taux de ponte')
        ->assertSee('Confiance')
        ->assertSee('Comment cette prévision est calculée');
});
