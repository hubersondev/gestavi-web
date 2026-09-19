<?php

use App\Enums\StockLotStatus;
use App\Enums\StockMovementReason;
use App\Models\Organisation;
use App\Models\Stock;
use App\Models\StockLot;
use App\Services\StockService;

/**
 * Valorisation au coût moyen unitaire pondéré et sorties FIFO/FEFO par lots.
 */
beforeEach(function () {
    $this->organisation = Organisation::factory()->create();
    $this->service = app(StockService::class);
});

it('recalcule le prix unitaire en CMUP à chaque entrée valorisée', function () {
    $stock = Stock::factory()->create(['organisation_id' => $this->organisation->id, 'quantity' => 100, 'unit_price' => 200]);

    // (100 × 200 + 100 × 300) / 200 = 250
    $stock->recordIn(100, StockMovementReason::Purchase, 300);

    expect((float) $stock->fresh()->quantity)->toBe(200.0)
        ->and((float) $stock->fresh()->unit_price)->toBe(250.0)
        ->and((float) $stock->unit_price)->toBe(250.0);

    // Entrée sans prix : le CMUP est conservé
    $stock->recordIn(50);

    expect((float) $stock->fresh()->unit_price)->toBe(250.0);

    // (250 × 250 + 50 × 400) / 300 = 275
    $stock->recordIn(50, StockMovementReason::Purchase, 400);

    expect((float) $stock->fresh()->unit_price)->toBe(275.0)
        ->and(Stock::weightedAverageCost(0, 0, 10, 1200))->toBe(1200.0);
});

it('consomme les lots par date de péremption la plus proche et met à jour leur valeur', function () {
    $stock = Stock::factory()->create(['organisation_id' => $this->organisation->id, 'quantity' => 60, 'unit_price' => 250]);

    $late = StockLot::create(['stock_id' => $stock->id, 'lot_number' => 'L-TARD', 'reception_date' => now()->subDays(10)->toDateString(), 'expiry_date' => now()->addDays(30)->toDateString(), 'initial_quantity' => 30, 'unit_price' => 250]);
    $soon = StockLot::create(['stock_id' => $stock->id, 'lot_number' => 'L-PROCHE', 'reception_date' => now()->subDays(2)->toDateString(), 'expiry_date' => now()->addDays(5)->toDateString(), 'initial_quantity' => 30, 'unit_price' => 250]);

    expect((float) $soon->fresh()->total_value)->toBe(7500.0);

    $movements = $this->service->consumeFromLots($stock, 40);

    expect($movements)->toHaveCount(2)
        // Le lot qui périme en premier est vidé d'abord (FEFO), puis le suivant
        ->and((float) $soon->fresh()->current_quantity)->toBe(0.0)
        ->and($soon->fresh()->status)->toBe(StockLotStatus::Depleted)
        ->and((float) $soon->fresh()->total_value)->toBe(0.0)
        ->and((float) $late->fresh()->current_quantity)->toBe(20.0)
        ->and((float) $late->fresh()->total_value)->toBe(5000.0)
        ->and((float) $stock->fresh()->quantity)->toBe(20.0)
        ->and($movements->first()->stock_lot_id)->toBe($soon->id);
});

it('sort le reliquat non tracé par lot en mouvement simple', function () {
    $stock = Stock::factory()->create(['organisation_id' => $this->organisation->id, 'quantity' => 100, 'unit_price' => 100]);
    StockLot::create(['stock_id' => $stock->id, 'lot_number' => 'L-1', 'reception_date' => now()->toDateString(), 'initial_quantity' => 30, 'unit_price' => 100]);

    $movements = $this->service->consumeFromLots($stock, 50, reason: StockMovementReason::Sale);

    expect($movements)->toHaveCount(2)
        ->and((float) $movements->first()->quantity)->toBe(30.0)
        ->and((float) $movements->last()->quantity)->toBe(20.0)
        ->and($movements->last()->stock_lot_id)->toBeNull()
        ->and($movements->last()->reason)->toBe(StockMovementReason::Sale)
        ->and((float) $stock->fresh()->quantity)->toBe(50.0);
});

it('la sortie de service choisit le FIFO quand des lots existent, la sortie simple sinon', function () {
    $withLots = Stock::factory()->create(['organisation_id' => $this->organisation->id, 'quantity' => 30, 'unit_price' => 100]);
    StockLot::create(['stock_id' => $withLots->id, 'lot_number' => 'L-A', 'reception_date' => now()->toDateString(), 'initial_quantity' => 30, 'unit_price' => 100]);

    $withoutLots = Stock::factory()->create(['organisation_id' => $this->organisation->id, 'quantity' => 30, 'unit_price' => 100]);

    $a = $this->service->recordOut($withLots, 10);
    $b = $this->service->recordOut($withoutLots, 10);

    expect($a->first()->stock_lot_id)->not->toBeNull()
        ->and($b->first()->stock_lot_id)->toBeNull()
        ->and((float) $withLots->fresh()->quantity)->toBe(20.0)
        ->and((float) $withoutLots->fresh()->quantity)->toBe(20.0);
});
