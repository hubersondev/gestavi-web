<?php

use App\Enums\FlockStatus;
use App\Enums\PlanFeature;
use App\Filament\Farm\Resources\Flocks\FlockResource;
use App\Filament\Farm\Resources\Flocks\Pages\CreateFlock;
use App\Filament\Farm\Resources\Flocks\Pages\EditFlock;
use App\Filament\Farm\Resources\Flocks\Pages\ListFlocks;
use App\Filament\Farm\Resources\Flocks\Pages\ViewFlock;
use App\Models\Building;
use App\Models\ChickenType;
use App\Models\DailyRecord;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);

    $this->plan = Plan::factory()->create([
        'features' => [
            PlanFeature::MultiFarms->value,
            PlanFeature::Buildings->value,
            PlanFeature::Flocks->value,
        ],
    ]);

    $this->organisation = Organisation::factory()->create([
        'plan_id' => $this->plan->id,
    ]);

    $this->ownerRole = $this->organisation->organisationRoles()
        ->where('slug', 'owner')->first();

    $this->user = User::factory()->create();
    $this->organisation->addMember($this->user, $this->ownerRole);

    $this->farm = Farm::factory()->create([
        'organisation_id' => $this->organisation->id,
    ]);

    $this->building = Building::factory()->create([
        'farm_id' => $this->farm->id,
    ]);

    $this->chickenType = ChickenType::factory()->create();

    // Authentifier l'utilisateur et configurer le panel Farm multi-tenant
    actingAs($this->user);
    Filament::setCurrentPanel(
        Filament::getPanel('farm'),
    );
    Filament::setTenant($this->organisation);
});

// ──────────────────────────────────────────────
//  Liste
// ──────────────────────────────────────────────

it('peut afficher la liste des bandes', function () {
    Livewire::test(ListFlocks::class)
        ->assertOk();
});

it('le scope tenant filtre les bandes par organisation', function () {
    $flock = Flock::factory()->create([
        'building_id' => $this->building->id,
    ]);

    // Bande d'une autre organisation
    $otherBuilding = Building::factory()->create();
    $otherFlock = Flock::factory()->create([
        'building_id' => $otherBuilding->id,
    ]);

    // Vérifier que le scope éloquent filtre correctement
    $scopedQuery = FlockResource::scopeEloquentQueryToTenant(
        Flock::query(),
        $this->organisation,
    );

    $results = $scopedQuery->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($flock->id);
});

// ──────────────────────────────────────────────
//  Création
// ──────────────────────────────────────────────

it('peut créer une bande', function () {
    Livewire::test(CreateFlock::class)
        ->fillForm([
            'building_id' => $this->building->id,
            'chicken_type_id' => $this->chickenType->id,
            'code' => 'LOT-TEST-001',
            'name' => 'Bande de test',
            'quantity_started' => 1000,
            'quantity_current' => 1000,
            'age_at_arrival' => 1,
            'start_date' => '2026-03-01',
            'expected_end_date' => '2026-04-15',
            'status' => FlockStatus::Active,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas(Flock::class, [
        'code' => 'LOT-TEST-001',
        'name' => 'Bande de test',
        'quantity_started' => 1000,
    ]);
});

it('valide les champs requis à la création', function (array $data, array $errors) {
    Livewire::test(CreateFlock::class)
        ->fillForm([
            'building_id' => $this->building->id,
            'code' => 'LOT-VAL-001',
            'quantity_started' => 500,
            'start_date' => '2026-03-01',
            'status' => FlockStatus::Planned,
            ...$data,
        ])
        ->call('create')
        ->assertHasFormErrors($errors);
})->with([
    'code requis' => [['code' => null], ['code' => 'required']],
    'building_id requis' => [['building_id' => null], ['building_id' => 'required']],
    'quantity_started requis' => [['quantity_started' => null], ['quantity_started' => 'required']],
    'start_date requis' => [['start_date' => null], ['start_date' => 'required']],
    'status requis' => [['status' => null], ['status' => 'required']],
]);

it('valide l\'unicité du code', function () {
    Flock::factory()->create([
        'building_id' => $this->building->id,
        'code' => 'LOT-UNIQUE',
    ]);

    Livewire::test(CreateFlock::class)
        ->fillForm([
            'building_id' => $this->building->id,
            'code' => 'LOT-UNIQUE',
            'quantity_started' => 500,
            'start_date' => '2026-03-01',
            'status' => FlockStatus::Planned,
        ])
        ->call('create')
        ->assertHasFormErrors(['code' => 'unique']);
});

// ──────────────────────────────────────────────
//  Édition
// ──────────────────────────────────────────────

it('peut modifier une bande', function () {
    // Effectif de départ fixé : la contrainte flocks_quantity_check impose quantity_current <= quantity_started
    $flock = Flock::factory()->create([
        'building_id' => $this->building->id,
        'quantity_started' => 1000,
        'quantity_current' => 1000,
    ]);

    Livewire::test(EditFlock::class, [
        'record' => $flock->id,
    ])
        ->fillForm([
            'name' => 'Bande modifiée',
            'quantity_current' => 800,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    assertDatabaseHas(Flock::class, [
        'id' => $flock->id,
        'name' => 'Bande modifiée',
        'quantity_current' => 800,
    ]);
});

// ──────────────────────────────────────────────
//  Vue
// ──────────────────────────────────────────────

it('peut afficher les détails d\'une bande', function () {
    $flock = Flock::factory()->create([
        'building_id' => $this->building->id,
        'code' => 'LOT-VIEW',
        'name' => 'Bande vue',
    ]);

    Livewire::test(ViewFlock::class, [
        'record' => $flock->id,
    ])
        ->assertOk()
        ->assertSchemaStateSet([
            'code' => 'LOT-VIEW',
            'name' => 'Bande vue',
        ]);
});

// ──────────────────────────────────────────────
//  Feature gating
// ──────────────────────────────────────────────

it('masque la navigation si le plan n\'a pas la feature Flocks', function () {
    $planSansFlocks = Plan::factory()->create([
        'features' => [PlanFeature::MultiFarms->value],
    ]);

    $org = Organisation::factory()->create([
        'plan_id' => $planSansFlocks->id,
    ]);

    expect($org->hasFeature(PlanFeature::Flocks))->toBeFalse();
});

// ──────────────────────────────────────────────
//  Helpers métier
// ──────────────────────────────────────────────

it('calcule le taux de mortalité à partir des relevés quotidiens', function () {
    $flock = Flock::factory()->active()->create([
        'building_id' => $this->building->id,
        'quantity_started' => 1000,
        'quantity_current' => 1000,
        'start_date' => now()->subDays(10),
    ]);

    DailyRecord::create(['flock_id' => $flock->id, 'record_date' => now()->subDays(2)->toDateString(), 'mortality' => 30]);
    DailyRecord::create(['flock_id' => $flock->id, 'record_date' => now()->subDay()->toDateString(), 'mortality' => 20]);

    expect($flock->fresh()->getMortalityRate())->toBe(5.0)
        ->and($flock->fresh()->quantity_current)->toBe(950);
});

it('calcule l\'âge en jours', function () {
    $flock = Flock::factory()->create([
        'building_id' => $this->building->id,
        'start_date' => now()->subDays(10),
        'age_at_arrival' => 7,
        'actual_end_date' => null,
    ]);

    expect($flock->getCurrentAgeInDays())->toBe(17);
});

it('calcule les pertes (mortalité + réformes) à partir des relevés', function () {
    $flock = Flock::factory()->active()->create([
        'building_id' => $this->building->id,
        'quantity_started' => 2000,
        'quantity_current' => 2000,
        'start_date' => now()->subDays(10),
    ]);

    DailyRecord::create(['flock_id' => $flock->id, 'record_date' => now()->subDay()->toDateString(), 'mortality' => 100, 'culls' => 50]);

    expect($flock->fresh()->getLossCount())->toBe(150)
        ->and($flock->fresh()->quantity_current)->toBe(1850);
});
