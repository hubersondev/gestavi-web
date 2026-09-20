<?php

use App\Enums\PlanFeature;
use App\Enums\VaccinationStatus;
use App\Filament\Farm\Resources\Slaughters\Pages\CreateSlaughter;
use App\Models\Building;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\Slaughter;
use App\Models\Stock;
use App\Models\User;
use App\Models\Vaccination;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * Abattages : sortie de sujets, entrée de carcasses, garde-fous effectif et délai d'attente.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);

    $plan = Plan::factory()->create(['features' => collect(PlanFeature::cases())->map->value->all()]);
    $this->organisation = Organisation::factory()->create(['plan_id' => $plan->id]);
    $this->user = User::factory()->create();
    $this->organisation->addMember($this->user, $this->organisation->organisationRoles()->where('slug', 'owner')->first());

    $this->farm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);
    $building = Building::factory()->create(['farm_id' => $this->farm->id]);

    $this->flock = Flock::factory()->active()->create([
        'building_id' => $building->id,
        'quantity_started' => 1000,
        'quantity_current' => 1000,
        'start_date' => now()->subDays(40),
    ]);

    actingAs($this->user);
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($this->organisation);
});

it('décrémente l\'effectif, calcule le rendement et alimente le stock de carcasses en kg', function () {
    $carcasses = Stock::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'unit' => 'kg', 'quantity' => 0, 'unit_price' => 0]);

    $slaughter = Slaughter::create([
        'flock_id' => $this->flock->id,
        'slaughter_date' => now()->toDateString(),
        'birds_count' => 300,
        'live_weight_kg' => 660,
        'carcass_weight_kg' => 495,
        'destination_stock_id' => $carcasses->id,
    ]);

    $flock = $this->flock->fresh();

    expect($slaughter->organisation_id)->toBe($this->organisation->id)
        ->and($slaughter->farm_id)->toBe($this->farm->id)
        ->and((float) $slaughter->yield_percent)->toBe(75.0)
        ->and($flock->quantity_slaughtered)->toBe(300)
        ->and($flock->quantity_current)->toBe(700)
        ->and($flock->birdsPresentOn(now()))->toBe(700)
        ->and($flock->birdsPresentOn(now()->subDay()))->toBe(1000)
        ->and((float) $carcasses->fresh()->quantity)->toBe(495.0)
        ->and($slaughter->fresh()->stock_movement_id)->not->toBeNull();
});

it('refuse un abattage supérieur à l\'effectif présent', function () {
    expect(fn () => Slaughter::create([
        'flock_id' => $this->flock->id,
        'slaughter_date' => now()->toDateString(),
        'birds_count' => 1500,
    ]))->toThrow(InvalidArgumentException::class);

    expect($this->flock->fresh()->quantity_current)->toBe(1000);
});

it('refuse un abattage pendant le délai d\'attente d\'un traitement', function () {
    $vaccination = Vaccination::create([
        'flock_id' => $this->flock->id,
        'treatment_name' => 'Antibiotique',
        'treatment_type' => 'antibiotique',
        'scheduled_date' => now()->toDateString(),
        'withdrawal_days' => 10,
        'status' => VaccinationStatus::Pending,
    ]);
    $vaccination->markAsDone(['administered_date' => now()->toDateString()]);

    expect(fn () => Slaughter::create([
        'flock_id' => $this->flock->id,
        'slaughter_date' => now()->toDateString(),
        'birds_count' => 10,
    ]))->toThrow(LogicException::class);
});

it('la page de création traduit un refus en notification sans enregistrer', function () {
    Livewire::test(CreateSlaughter::class)
        ->fillForm([
            'flock_id' => $this->flock->id,
            'slaughter_date' => now()->toDateString(),
            'birds_count' => 200,
            'live_weight_kg' => 440,
            'carcass_weight_kg' => 330,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Slaughter::query()->where('flock_id', $this->flock->id)->count())->toBe(1)
        ->and($this->flock->fresh()->quantity_current)->toBe(800);
});
