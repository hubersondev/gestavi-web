<?php

use App\Models\DailyRecord;
use App\Models\Flock;
use App\Models\Organisation;
use App\Models\Sale;

/**
 * L'effectif courant est dérivé : initial − mortalité − réformes − sujets vendus.
 */
beforeEach(function () {
    $this->organisation = Organisation::factory()->create();
    $this->flock = Flock::factory()->active()->create([
        'quantity_started' => 1000,
        'quantity_current' => 1000,
        'start_date' => now()->subDays(30),
        'age_at_arrival' => 0,
    ]);
});

it('déduit mortalité et réformes de l\'effectif et renseigne l\'effectif présent du jour', function () {
    $r1 = DailyRecord::create(['flock_id' => $this->flock->id, 'record_date' => now()->subDays(10)->toDateString(), 'mortality' => 10, 'culls' => 5]);
    $r2 = DailyRecord::create(['flock_id' => $this->flock->id, 'record_date' => now()->subDays(9)->toDateString(), 'mortality' => 3]);

    $flock = $this->flock->fresh();

    expect($flock->quantity_current)->toBe(982)
        ->and($flock->getTotalMortality())->toBe(13)
        ->and($flock->getTotalCulls())->toBe(5)
        ->and($flock->getMortalityRate())->toBe(1.3)
        ->and($flock->getCullRate())->toBe(0.5)
        ->and($r1->fresh()->birds_present)->toBe(985)
        ->and($r2->fresh()->birds_present)->toBe(982);
});

it('une correction rétroactive se propage aux relevés postérieurs', function () {
    $r1 = DailyRecord::create(['flock_id' => $this->flock->id, 'record_date' => now()->subDays(10)->toDateString(), 'mortality' => 10]);
    $r2 = DailyRecord::create(['flock_id' => $this->flock->id, 'record_date' => now()->subDays(9)->toDateString(), 'mortality' => 5]);

    $r1->update(['mortality' => 2]);

    expect($this->flock->fresh()->quantity_current)->toBe(993)
        ->and($r1->fresh()->birds_present)->toBe(998)
        ->and($r2->fresh()->birds_present)->toBe(993);

    $r1->delete();

    expect($this->flock->fresh()->quantity_current)->toBe(995)
        ->and($r2->fresh()->birds_present)->toBe(995);
});

it('une vente de sujets vivants décrémente la bande, une annulation la restitue', function () {
    $sale = Sale::factory()->create(['organisation_id' => $this->organisation->id, 'sale_date' => now()->toDateString()]);

    $sale->items()->create([
        'product_name' => 'Poulets vifs',
        'flock_id' => $this->flock->id,
        'is_live_birds' => true,
        'quantity' => 200,
        'unit_price' => 3500,
    ]);

    // Ligne d'œufs liée à la bande : ne touche pas l'effectif
    $sale->items()->create([
        'product_name' => 'Œufs',
        'flock_id' => $this->flock->id,
        'is_live_birds' => false,
        'quantity' => 30,
        'unit_price' => 100,
    ]);

    $flock = $this->flock->fresh();

    expect($flock->quantity_sold)->toBe(200)
        ->and($flock->quantity_current)->toBe(800)
        ->and($flock->getMortalityRate())->toBe(0.0);

    $sale->cancel();
    $flock->recalculateQuantities();

    expect($flock->fresh()->quantity_current)->toBe(1000);
});

it('refuse des pertes supérieures à l\'effectif présent', function () {
    $this->flock->update(['quantity_current' => 10]);

    expect(fn () => DailyRecord::create([
        'flock_id' => $this->flock->id,
        'record_date' => now()->toDateString(),
        'mortality' => 8,
        'culls' => 5,
    ]))->toThrow(InvalidArgumentException::class);
});

it('les taux journaliers utilisent l\'effectif présent', function () {
    DailyRecord::create(['flock_id' => $this->flock->id, 'record_date' => now()->subDays(2)->toDateString(), 'mortality' => 500]);
    $record = DailyRecord::create([
        'flock_id' => $this->flock->id,
        'record_date' => now()->subDay()->toDateString(),
        'mortality' => 5,
        'eggs_collected' => 396,
        'feed_consumed_kg' => 49.5,
    ]);

    $record = $record->fresh();

    expect($record->birds_present)->toBe(495)
        ->and($record->getMortalityRate())->toBe(1.0)   // 5 morts / 500 présents en début de journée
        ->and($record->getLayingRate())->toBe(80.0)     // 396 œufs / 495 poules
        ->and($record->getFeedPerBird())->toBe(100.0);  // 49,5 kg / 495 sujets
});
