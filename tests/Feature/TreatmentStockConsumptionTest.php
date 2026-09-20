<?php

use App\Enums\IncidentStatus;
use App\Enums\StockCategoryType;
use App\Enums\VaccinationStatus;
use App\Models\Building;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\HealthIncident;
use App\Models\Organisation;
use App\Models\Stock;
use App\Models\StockCategory;
use App\Models\Vaccination;
use App\Services\FlockCostService;

/**
 * Vaccins et traitements prélevés sur le stock de médicaments, imputés à la bande.
 * Le coût du produit est porté par le mouvement de stock (CMUP), jamais compté deux fois.
 */
beforeEach(function () {
    $this->organisation = Organisation::factory()->create();
    $this->farm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);
    $building = Building::factory()->create(['farm_id' => $this->farm->id]);

    $this->flock = Flock::factory()->active()->create([
        'building_id' => $building->id,
        'quantity_started' => 1000,
        'quantity_current' => 1000,
        'start_date' => now()->subDays(20),
    ]);

    $category = StockCategory::create(['organisation_id' => $this->organisation->id, 'name' => 'Médicaments', 'slug' => 'medic-'.uniqid(), 'type' => StockCategoryType::Medicine]);

    $this->medicine = Stock::factory()->create([
        'organisation_id' => $this->organisation->id,
        'farm_id' => $this->farm->id,
        'stock_category_id' => $category->id,
        'quantity' => 50,
        'unit_price' => 4000,
        'unit' => 'flacon',
    ]);
});

it('une vaccination effectuée consomme le stock et la valeur entre dans le coût santé de la bande', function () {
    $vaccination = Vaccination::create([
        'flock_id' => $this->flock->id,
        'treatment_name' => 'Gumboro',
        'treatment_type' => 'vaccin',
        'scheduled_date' => now()->toDateString(),
        'status' => VaccinationStatus::Pending,
    ]);

    $vaccination->markAsDone([
        'administered_date' => now()->toDateString(),
        'stock_id' => $this->medicine->id,
        'stock_quantity_used' => 2,
    ]);

    $vaccination->refresh();

    expect($vaccination->isDone())->toBeTrue()
        ->and((float) $this->medicine->fresh()->quantity)->toBe(48.0)
        ->and($vaccination->stock_movement_id)->not->toBeNull()
        ->and((float) $vaccination->stock_quantity_used)->toBe(2.0)
        ->and($vaccination->getStockValue())->toBe(8000.0)
        ->and($vaccination->cost)->toBeNull();

    $breakdown = app(FlockCostService::class)->breakdown($this->flock->fresh());

    expect($breakdown['couts']['sante'])->toBe(8000.0);
});

it('un coût hors stock saisi sur la vaccination s\'ajoute à la valeur du produit prélevé', function () {
    $vaccination = Vaccination::create([
        'flock_id' => $this->flock->id,
        'treatment_name' => 'Newcastle',
        'treatment_type' => 'vaccin',
        'scheduled_date' => now()->toDateString(),
        'status' => VaccinationStatus::Pending,
    ]);

    $vaccination->markAsDone(['cost' => 2500, 'stock_id' => $this->medicine->id, 'stock_quantity_used' => 1]);

    expect((float) $vaccination->fresh()->cost)->toBe(2500.0)
        ->and(app(FlockCostService::class)->breakdown($this->flock->fresh())['couts']['sante'])->toBe(6500.0);
});

it('le démarrage d\'un traitement d\'incident consomme le stock', function () {
    $incident = HealthIncident::create([
        'flock_id' => $this->flock->id,
        'title' => 'Coccidiose',
        'incident_date' => now()->toDateString(),
        'severity' => 'medium',
        'category' => 'parasitic',
        'symptoms' => 'Fientes sanguinolentes, abattement',
        'status' => IncidentStatus::Detected,
        'birds_affected' => 50,
    ]);

    $incident->startTreatment('Anticoccidien dans l\'eau', now()->toDateString(), $this->medicine->id, 3);

    $incident->refresh();

    expect($incident->status)->toBe(IncidentStatus::UnderTreatment)
        ->and((float) $this->medicine->fresh()->quantity)->toBe(47.0)
        ->and($incident->treatment_movement_id)->not->toBeNull()
        ->and((float) $incident->treatment_stock_quantity)->toBe(3.0)
        ->and(app(FlockCostService::class)->breakdown($this->flock->fresh())['couts']['sante'])->toBe(12000.0);
});
