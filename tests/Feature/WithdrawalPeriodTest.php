<?php

use App\Enums\VaccinationStatus;
use App\Exceptions\CommercialException;
use App\Models\Building;
use App\Models\ChickenType;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\HealthProgram;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vaccination;
use App\Services\FlockService;
use App\Services\SaleService;

use function Pest\Laravel\actingAs;

/**
 * Délai d'attente sanitaire : copié depuis le programme, calculé à l'administration,
 * bloquant pour la vente et la clôture de bande.
 */
beforeEach(function () {
    // Le journal de ferme exige un auteur (recorded_by NOT NULL)
    actingAs(User::factory()->create());

    $this->organisation = Organisation::factory()->create();
    $farm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);
    $building = Building::factory()->create(['farm_id' => $farm->id]);
    $this->chickenType = ChickenType::factory()->create(['name' => 'Chair', 'production_kind' => 'broiler']);

    $this->flock = Flock::factory()->active()->create([
        'building_id' => $building->id,
        'chicken_type_id' => $this->chickenType->id,
        'start_date' => now()->subDays(20),
        'age_at_arrival' => 0,
    ]);
});

it('copie le délai d\'attente du programme sur la vaccination générée', function () {
    HealthProgram::create([
        'organisation_id' => null,
        'chicken_type_id' => $this->chickenType->id,
        'treatment_name' => 'Antibiotique',
        'treatment_type' => 'antibiotique',
        'scheduled_day' => 5,
        'withdrawal_days' => 7,
    ]);

    app(FlockService::class)->generateVaccinationCalendar($this->flock);

    expect($this->flock->vaccinations()->first()->withdrawal_days)->toBe(7);
});

it('calcule la date de fin du délai à l\'administration et bloque vente et clôture', function () {
    $vaccination = Vaccination::create([
        'flock_id' => $this->flock->id,
        'treatment_name' => 'Antibiotique',
        'treatment_type' => 'antibiotique',
        'scheduled_date' => now()->subDays(2)->toDateString(),
        'withdrawal_days' => 10,
        'status' => VaccinationStatus::Pending,
    ]);

    $vaccination->markAsDone(['administered_date' => now()->subDays(2)->toDateString()]);

    $flock = $this->flock->fresh();

    expect($vaccination->fresh()->withdrawal_until->toDateString())->toBe(now()->addDays(8)->toDateString())
        ->and($flock->isUnderWithdrawal())->toBeTrue()
        ->and($flock->isUnderWithdrawal(now()->addDays(9)))->toBeFalse();

    expect(fn () => app(SaleService::class)->createSale(
        ['organisation_id' => $this->organisation->id, 'sale_date' => now()->toDateString()],
        [['product_name' => 'Poulets', 'flock_id' => $flock->id, 'is_live_birds' => true, 'quantity' => 10, 'unit_price' => 3000]],
    ))->toThrow(CommercialException::class);

    expect(fn () => app(FlockService::class)->completeFlock($flock))->toThrow(LogicException::class);

    // Passé le délai, la clôture redevient possible
    $this->travel(9)->days();
    expect($flock->fresh()->isUnderWithdrawal())->toBeFalse();
    app(FlockService::class)->completeFlock($flock->fresh());
    expect($flock->fresh()->isCompleted())->toBeTrue();
});

it('une vaccination sans délai d\'attente ne bloque rien', function () {
    $vaccination = Vaccination::create([
        'flock_id' => $this->flock->id,
        'treatment_name' => 'Vitamines',
        'treatment_type' => 'vitamine',
        'scheduled_date' => now()->toDateString(),
        'withdrawal_days' => 0,
        'status' => VaccinationStatus::Pending,
    ]);

    $vaccination->markAsDone();

    expect($vaccination->fresh()->withdrawal_until)->toBeNull()
        ->and($this->flock->fresh()->isUnderWithdrawal())->toBeFalse();
});
