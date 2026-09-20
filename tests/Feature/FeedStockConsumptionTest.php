<?php

use App\Enums\StockCategoryType;
use App\Exceptions\StockException;
use App\Models\Building;
use App\Models\DailyRecord;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\Organisation;
use App\Models\Stock;
use App\Models\StockCategory;
use App\Models\StockMovement;
use App\Services\FlockCostService;
use App\Services\FlockService;

/**
 * L'aliment consommé sur un relevé est prélevé du stock choisi et alimente le coût de revient.
 */
beforeEach(function () {
    $this->organisation = Organisation::factory()->create();
    $this->farm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);
    $building = Building::factory()->create(['farm_id' => $this->farm->id]);

    $this->flock = Flock::factory()->active()->create([
        'building_id' => $building->id,
        'quantity_started' => 500,
        'quantity_current' => 500,
        'start_date' => now()->subDays(10),
    ]);

    $category = StockCategory::create(['organisation_id' => $this->organisation->id, 'name' => 'Aliment', 'slug' => 'aliment-'.uniqid(), 'type' => StockCategoryType::Feed]);

    $this->feed = Stock::factory()->create([
        'organisation_id' => $this->organisation->id,
        'farm_id' => $this->farm->id,
        'stock_category_id' => $category->id,
        'quantity' => 1000,
        'unit_price' => 350,
        'unit' => 'kg',
    ]);
});

it('déstocke l\'aliment du relevé et l\'impute à la bande', function () {
    $record = app(FlockService::class)->recordDailyData($this->flock, [
        'record_date' => now()->toDateString(),
        'mortality' => 0,
        'feed_consumed_kg' => 120,
        'feed_stock_id' => $this->feed->id,
    ]);

    $record->refresh();

    expect((float) $this->feed->fresh()->quantity)->toBe(880.0)
        ->and($record->feed_movement_id)->not->toBeNull()
        ->and(StockMovement::query()->find($record->feed_movement_id)->flock_id)->toBe($this->flock->id);

    $breakdown = app(FlockCostService::class)->breakdown($this->flock->fresh());

    expect($breakdown['couts']['aliment'])->toBe(42000.0); // 120 kg × 350
});

it('une correction du relevé régularise la différence par un mouvement d\'ajustement', function () {
    $service = app(FlockService::class);

    $record = $service->recordDailyData($this->flock, [
        'record_date' => now()->toDateString(),
        'feed_consumed_kg' => 100,
        'feed_stock_id' => $this->feed->id,
    ]);

    $service->adjustFeedConsumption($record->fresh(), 100, 130);
    expect((float) $this->feed->fresh()->quantity)->toBe(870.0);

    $service->adjustFeedConsumption($record->fresh(), 130, 90);
    expect((float) $this->feed->fresh()->quantity)->toBe(910.0);

    expect(StockMovement::query()->where('flock_id', $this->flock->id)->count())->toBe(3);
});

it('refuse un prélèvement supérieur au stock disponible sans créer de relevé', function () {
    expect(fn () => app(FlockService::class)->recordDailyData($this->flock, [
        'record_date' => now()->toDateString(),
        'feed_consumed_kg' => 5000,
        'feed_stock_id' => $this->feed->id,
    ]))->toThrow(StockException::class);

    expect((float) $this->feed->fresh()->quantity)->toBe(1000.0)
        ->and(DailyRecord::query()->where('flock_id', $this->flock->id)->count())->toBe(0);
});

it('un relevé sans stock d\'aliment ne touche pas les stocks', function () {
    app(FlockService::class)->recordDailyData($this->flock, [
        'record_date' => now()->toDateString(),
        'feed_consumed_kg' => 50,
    ]);

    expect((float) $this->feed->fresh()->quantity)->toBe(1000.0)
        ->and(StockMovement::query()->where('flock_id', $this->flock->id)->count())->toBe(0);
});
