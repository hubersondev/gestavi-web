<?php

use App\Models\Building;
use App\Models\ChickenType;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\HealthProgram;
use App\Models\Organisation;
use App\Services\FlockService;

/**
 * Les programmes sanitaires de référence (organisation_id NULL) doivent
 * s'appliquer à toutes les organisations, en plus de leurs propres programmes.
 */
beforeEach(function () {
    $this->chickenType = ChickenType::factory()->create(['name' => 'Chair']);

    $this->organisation = Organisation::factory()->create();
    $this->otherOrganisation = Organisation::factory()->create();

    $farm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);
    $building = Building::factory()->create(['farm_id' => $farm->id]);

    $this->flock = Flock::factory()->planned()->create([
        'building_id' => $building->id,
        'chicken_type_id' => $this->chickenType->id,
        'start_date' => now(),
        'age_at_arrival' => 0,
    ]);

    // Programme de référence (livré avec l'application)
    HealthProgram::create([
        'organisation_id' => null,
        'chicken_type_id' => $this->chickenType->id,
        'treatment_name' => 'Newcastle HB1 (référence)',
        'treatment_type' => 'vaccin',
        'scheduled_day' => 7,
    ]);

    // Programme propre à l'organisation
    HealthProgram::create([
        'organisation_id' => $this->organisation->id,
        'chicken_type_id' => $this->chickenType->id,
        'treatment_name' => 'Gumboro (organisation)',
        'treatment_type' => 'vaccin',
        'scheduled_day' => 14,
    ]);

    // Programme d'une autre organisation : ne doit jamais apparaître
    HealthProgram::create([
        'organisation_id' => $this->otherOrganisation->id,
        'chicken_type_id' => $this->chickenType->id,
        'treatment_name' => 'Programme étranger',
        'treatment_type' => 'vaccin',
        'scheduled_day' => 21,
    ]);
});

it('retourne les programmes de référence et ceux de l\'organisation, pas ceux des autres', function () {
    $programs = HealthProgram::getProgramForFlock($this->flock);

    expect($programs->pluck('treatment_name')->all())
        ->toBe(['Newcastle HB1 (référence)', 'Gumboro (organisation)']);
});

it('génère le calendrier vaccinal à partir des programmes de référence', function () {
    $count = app(FlockService::class)->generateVaccinationCalendar($this->flock);

    expect($count)->toBe(2)
        ->and($this->flock->vaccinations()->count())->toBe(2)
        ->and($this->flock->vaccinations()->pluck('treatment_name')->all())
        ->not->toContain('Programme étranger');
});
