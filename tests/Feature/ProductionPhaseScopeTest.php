<?php

use App\Models\Building;
use App\Models\ChickenType;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\Organisation;
use App\Models\ProductionPhase;

/**
 * Chaque organisation possède son propre paramétrage de phases : une bande
 * doit toujours lire la phase de SON organisation, jamais celle d'une autre.
 */
it('retourne la phase de production de l\'organisation de la bande', function () {
    $chickenType = ChickenType::factory()->create(['name' => 'Chair']);

    // Les phases par défaut sont créées automatiquement pour chaque organisation
    $organisationA = Organisation::factory()->create();
    $organisationB = Organisation::factory()->create();

    ProductionPhase::query()
        ->where('organisation_id', $organisationB->id)
        ->where('code', 'CHAIR_START')
        ->update(['name' => 'Démarrage personnalisé B']);

    $farm = Farm::factory()->create(['organisation_id' => $organisationB->id]);
    $building = Building::factory()->create(['farm_id' => $farm->id]);

    $flock = Flock::factory()->active()->create([
        'building_id' => $building->id,
        'chicken_type_id' => $chickenType->id,
        'start_date' => now()->subDays(4),
        'age_at_arrival' => 0,
    ]);

    $phase = $flock->getCurrentPhase();

    expect($phase)->not->toBeNull()
        ->and($phase->organisation_id)->toBe($organisationB->id)
        ->and($phase->name)->toBe('Démarrage personnalisé B');

    expect(ProductionPhase::getPhaseForAge($chickenType->id, 5, $organisationA->id)?->name)
        ->toBe('Démarrage Chair');
});
