<?php

use App\Enums\PlanFeature;
use App\Enums\ProductionKind;
use App\Models\Building;
use App\Models\ChickenType;
use App\Models\City;
use App\Models\Country;
use App\Models\DailyRecord;
use App\Models\Farm;
use App\Models\FarmLog;
use App\Models\Flock;
use App\Models\NotificationPreference;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\ProductionPhase;
use App\Models\User;
use App\Models\WeatherForecast;
use App\Notifications\OrganisationAlertNotification;
use App\Services\Weather\WeatherService;
use Database\Seeders\ChickenTypeSeeder;
use Database\Seeders\OrganisationPermissionSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

/**
 * Météo : prévisions agrégées par jour, géocodage de la ville, alertes selon les seuils de la
 * phase, isolation des bâtiments, commande planifiée, API mobile, préférences.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);
    $this->seed(ChickenTypeSeeder::class);
    Notification::fake();
    config(['services.openweathermap.key' => 'cle-de-test', 'services.openweathermap.base_url' => 'https://api.openweathermap.org']);

    $plan = Plan::factory()->create(['features' => collect(PlanFeature::cases())->map->value->all()]);
    $this->organisation = Organisation::factory()->create(['plan_id' => $plan->id]);
    $roles = $this->organisation->organisationRoles()->get()->keyBy('slug');
    $this->owner = User::factory()->create();
    $this->organisation->addMember($this->owner, $roles['owner']);
    $this->farm = Farm::factory()->create(['organisation_id' => $this->organisation->id, 'name' => 'Ferme Anyama', 'latitude' => 5.4964, 'longitude' => -4.0548]);
    $this->building = Building::factory()->create(['farm_id' => $this->farm->id]);
    $this->service = app(WeatherService::class);
});

/**
 * Réponse OpenWeatherMap /forecast : deux tranches par jour sur $days jours à partir de demain,
 * avec un maximum de $tempMax le premier jour.
 */
function forecastPayload(int $days = 2, float $tempMax = 33.0, float $humidity = 70, float $rain = 0, float $wind = 3.0): array
{
    $list = [];

    for ($d = 1; $d <= $days; $d++) {
        $date = now()->addDays($d)->toDateString();
        $max = $d === 1 ? $tempMax : 31.0;

        foreach (['09:00:00' => $max - 6, '15:00:00' => $max] as $time => $temp) {
            $list[] = [
                'dt_txt' => "{$date} {$time}",
                'main' => ['temp' => $temp, 'temp_min' => $temp - 1, 'temp_max' => $temp, 'humidity' => $humidity, 'pressure' => 1010],
                'wind' => ['speed' => $wind, 'gust' => $wind + 2],
                'rain' => ['3h' => $rain],
                'clouds' => ['all' => 40],
                'weather' => [['description' => 'ciel dégagé', 'icon' => '01d']],
            ];
        }
    }

    return ['list' => $list];
}

/**
 * Simule OpenWeatherMap. Les faux de Laravel s'empilent (le premier enregistré répond toujours),
 * d'où un seul faux par test qui lit la charge utile courante.
 */
function fakeWeather(array $payload): void
{
    $GLOBALS['weatherPayload'] = $payload;

    if (($GLOBALS['weatherFakeRegistered'] ?? null) === spl_object_id(app())) {
        return;
    }

    $GLOBALS['weatherFakeRegistered'] = spl_object_id(app());

    Http::fake(fn ($request) => str_contains($request->url(), 'geo/1.0/direct')
        ? Http::response([['name' => 'Béoumi', 'lat' => 7.6736, 'lon' => -5.5808]])
        : Http::response($GLOBALS['weatherPayload']));
}

it('agrège les tranches de 3 heures en journées et enregistre les prévisions de la ferme', function () {
    fakeWeather(forecastPayload(days: 2, tempMax: 34, rain: 2.5));

    expect($this->service->fetchForecast($this->farm))->toBe(2);

    $day = WeatherForecast::query()->forFarm($this->farm->id)->orderBy('forecast_date')->first();

    expect($day->forecast_date->toDateString())->toBe(now()->addDay()->toDateString())
        ->and((float) $day->temp_max)->toBe(34.0)
        ->and((float) $day->temp_min)->toBe(27.0)
        ->and((float) $day->rain_mm)->toBe(5.0)
        ->and((float) $day->humidity)->toBe(70.0)
        ->and($day->icon)->toBe('01d')
        ->and($day->organisation_id)->toBe($this->organisation->id)
        ->and($day->temperatureRange())->toBe('27–34 °C');

    // Rejeu : mise à jour, pas de doublon
    fakeWeather(forecastPayload(days: 2, tempMax: 36));
    $this->service->fetchForecast($this->farm);
    expect(WeatherForecast::query()->forFarm($this->farm->id)->count())->toBe(2)
        ->and((float) WeatherForecast::query()->forFarm($this->farm->id)->orderBy('forecast_date')->first()->temp_max)->toBe(36.0);
});

it('géocode la ville d\'une ferme sans coordonnées et les mémorise', function () {
    $country = Country::query()->where('code', 'CI')->first() ?? Country::factory()->create(['code' => 'CI', 'name' => 'Côte d\'Ivoire']);
    $city = City::query()->firstOrCreate(['name' => 'Béoumi', 'country_id' => $country->id], ['is_active' => true]);
    $farm = Farm::factory()->create(['organisation_id' => $this->organisation->id, 'name' => 'Ferme Béoumi', 'city_id' => $city->id, 'latitude' => null, 'longitude' => null]);
    fakeWeather(forecastPayload());

    expect($this->service->fetchForecast($farm))->toBe(2)
        ->and((float) $farm->fresh()->latitude)->toBe(7.6736)
        ->and((float) $farm->fresh()->longitude)->toBe(-5.5808);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'geo/1.0/direct') && str_contains(urldecode($request->url()), 'Béoumi,CI'));
});

it('signale canicule, humidité, vent et pluie d\'après les seuils de la phase, alerte et journalise', function () {
    $broiler = ChickenType::query()->where('production_kind', ProductionKind::Broiler)->firstOrFail();
    $flock = Flock::factory()->active()->create(['building_id' => $this->building->id, 'chicken_type_id' => $broiler->id, 'start_date' => now()->subDays(20), 'age_at_arrival' => 0]);
    $phase = $flock->getCurrentPhase() ?? ProductionPhase::query()->create([
        'organisation_id' => $this->organisation->id, 'chicken_type_id' => $broiler->id, 'name' => 'Croissance', 'code' => 'CRO',
        'start_day' => 0, 'end_day' => 100, 'order' => 1, 'is_active' => true,
    ]);
    $phase->update(['max_temperature' => 30, 'min_temperature' => 20, 'humidity_max' => 60]);

    // Seuils de phase : 30 °C / 60 % (confort intérieur). Extérieur prévu : 43 °C (+13 → critique),
    // 95 % (+35 → humidité extrême) ; 37 °C ou 85 % n'alerteraient pas (marges 8 °C / 30 points)
    fakeWeather(forecastPayload(days: 3, tempMax: 43, humidity: 95, rain: 30, wind: 16));

    $this->artisan('gestavi:fetch-weather')->assertSuccessful();

    $anomalies = $this->service->anomaliesFor($this->farm);
    $kinds = collect($anomalies)->pluck('kind')->unique()->values()->all();

    expect($kinds)->toContain('heat', 'humidity', 'wind', 'rain')
        ->and(collect($anomalies)->firstWhere('kind', 'heat')['critical'])->toBeTrue()
        ->and(collect($anomalies)->firstWhere('kind', 'heat')['message'])->toContain('Canicule', '43 °C', 'seuil 30 °C');

    // Une journée tropicale ordinaire (37 °C, 85 %) ne déclenche rien face à un confort intérieur de 30 °C / 60 %
    $ordinary = WeatherForecast::query()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'forecast_date' => now()->addDays(10)->toDateString(), 'temp_max' => 37, 'temp_min' => 24, 'humidity' => 85]);
    expect($this->service->evaluate($ordinary, 30.0, 20.0, 60.0, 'Ponte'))->toBe([]);

    Notification::assertSentTo($this->owner, OrganisationAlertNotification::class, fn (OrganisationAlertNotification $n): bool => $n->alertKey === 'weather_critical'
        && $n->status === 'danger'
        && str_contains($n->lines[0], $flock->code)
        && str_contains($n->lines[0], 'Canicule'));
    Notification::assertSentTo($this->owner, OrganisationAlertNotification::class, fn (OrganisationAlertNotification $n): bool => $n->alertKey === 'weather_warning'
        && str_contains(implode(' ', $n->lines), 'Vent fort'));

    expect(FarmLog::query()->where('farm_id', $this->farm->id)->where('title', 'Alerte météo — Ferme Anyama')->count())->toBe(1);

    // Rejeu le même jour : ni nouvelle alerte ni nouvelle entrée de journal
    Notification::fake();
    $this->artisan('gestavi:fetch-weather')->assertSuccessful();
    Notification::assertNothingSent();
    expect(FarmLog::query()->where('farm_id', $this->farm->id)->count())->toBe(1);
});

it('évalue l\'isolation des bâtiments à partir des relevés intérieurs et de la météo du jour', function () {
    $flock = Flock::factory()->active()->create(['building_id' => $this->building->id, 'start_date' => now()->subDays(30), 'age_at_arrival' => 0]);
    WeatherForecast::query()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'forecast_date' => now()->toDateString(), 'temp_avg' => 34, 'temp_min' => 28, 'temp_max' => 38]);
    DailyRecord::create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'flock_id' => $flock->id, 'record_date' => now()->subDay()->toDateString(), 'mortality' => 0, 'culls' => 0, 'temperature_min' => 26, 'temperature_max' => 30]);

    $insulation = $this->service->buildingInsulation($this->farm);

    expect($insulation)->toHaveCount(1)
        ->and($insulation[0]['inside'])->toBe(28.0)
        ->and($insulation[0]['delta'])->toBe(-6.0)
        ->and($insulation[0]['score'])->toBe('bonne');
});

it('sans clé, la commande ne fait aucun appel et l\'API répond désactivée', function () {
    config(['services.openweathermap.key' => null]);
    Http::fake();

    $this->artisan('gestavi:fetch-weather')->expectsOutputToContain('désactivée')->assertSuccessful();
    Http::assertNothingSent();

    Sanctum::actingAs($this->owner);
    getJson("/api/v1/organisations/{$this->organisation->slug}/farms/{$this->farm->id}/weather")
        ->assertOk()
        ->assertJsonPath('enabled', false)
        ->assertJsonPath('located', true)
        ->assertJsonCount(0, 'forecast');
});

it('expose au mobile la météo de la ferme et respecte les préférences de notification météo', function () {
    fakeWeather(forecastPayload(days: 2, tempMax: 33));
    $this->service->fetchForecast($this->farm);

    Sanctum::actingAs($this->owner);
    $response = getJson("/api/v1/organisations/{$this->organisation->slug}/farms/{$this->farm->id}/weather")->assertOk();

    expect($response->json('enabled'))->toBeTrue()
        ->and($response->json('forecast'))->toHaveCount(2)
        ->and($response->json('forecast.0.temp_max'))->toEqual(33)
        ->and($response->json('forecast.0.emoji'))->toBe('☀️');

    config(['mail.default' => 'smtp']);
    NotificationPreference::query()->create(['user_id' => $this->owner->id, 'app_weather' => false, 'email_weather' => true]);
    $this->owner->refresh();

    $notification = new OrganisationAlertNotification(title: 'Météo', lines: ['• test'], alertKey: 'weather_warning', organisationId: $this->organisation->id);
    expect($notification->via($this->owner))->toBe(['mail']);
});
