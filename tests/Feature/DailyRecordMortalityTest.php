<?php

use App\Models\DailyRecord;
use App\Models\Flock;
use App\Services\FlockService;

/**
 * L'effectif courant d'une bande doit suivre la mortalité saisie,
 * y compris lors des corrections rétroactives et des suppressions.
 */
beforeEach(function () {
    $this->flock = Flock::factory()->active()->create([
        'quantity_started' => 1000,
        'quantity_current' => 1000,
        'start_date' => now()->subDays(10),
        'age_at_arrival' => 0,
    ]);
});

it('décrémente l\'effectif à la création d\'un relevé avec mortalité', function () {
    DailyRecord::create([
        'flock_id' => $this->flock->id,
        'record_date' => now()->subDays(1)->toDateString(),
        'mortality' => 12,
    ]);

    expect($this->flock->fresh()->quantity_current)->toBe(988);
});

it('applique le delta lors d\'une correction rétroactive de la mortalité', function () {
    $record = DailyRecord::create([
        'flock_id' => $this->flock->id,
        'record_date' => now()->subDays(1)->toDateString(),
        'mortality' => 12,
    ]);

    $record->update(['mortality' => 5]);

    expect($this->flock->fresh()->quantity_current)->toBe(995);
});

it('restitue les sujets lors de la suppression d\'un relevé', function () {
    $record = DailyRecord::create([
        'flock_id' => $this->flock->id,
        'record_date' => now()->subDays(1)->toDateString(),
        'mortality' => 30,
    ]);

    expect($this->flock->fresh()->quantity_current)->toBe(970);

    $record->delete();

    expect($this->flock->fresh()->quantity_current)->toBe(1000);
});

it('refuse une mortalité supérieure à l\'effectif présent, à la création comme en correction', function () {
    // L'effectif est dérivé des relevés : on part d'une petite bande de 10 sujets
    $small = Flock::factory()->active()->create([
        'quantity_started' => 10,
        'quantity_current' => 10,
        'start_date' => now()->subDays(10),
        'age_at_arrival' => 0,
    ]);

    expect(fn () => DailyRecord::create([
        'flock_id' => $small->id,
        'record_date' => now()->toDateString(),
        'mortality' => 50,
    ]))->toThrow(InvalidArgumentException::class);

    expect($small->fresh()->quantity_current)->toBe(10)
        ->and(DailyRecord::query()->where('flock_id', $small->id)->count())->toBe(0);

    $record = DailyRecord::create([
        'flock_id' => $small->id,
        'record_date' => now()->toDateString(),
        'mortality' => 4,
    ]);

    expect($small->fresh()->quantity_current)->toBe(6);

    // Passer de 4 à 11 morts demande 7 sujets de plus, il n'en reste que 6
    expect(fn () => $record->update(['mortality' => 11]))->toThrow(InvalidArgumentException::class);

    // Passer de 4 à 10 est possible (exactement l'effectif restant)
    $record->update(['mortality' => 10]);

    expect($small->fresh()->quantity_current)->toBe(0);
});

it('le service calcule l\'âge et refuse une mortalité supérieure à l\'effectif présent', function () {
    $service = app(FlockService::class);

    $record = $service->recordDailyData($this->flock, [
        'record_date' => now()->subDays(3)->toDateString(),
        'mortality' => 7,
    ]);

    expect($record->age_days)->toBe(7)
        ->and($this->flock->fresh()->quantity_current)->toBe(993);

    expect(fn () => $service->recordDailyData($this->flock, [
        'record_date' => now()->toDateString(),
        'mortality' => 5000,
    ]))->toThrow(InvalidArgumentException::class);
});

it('le taux de mortalité de la bande reflète les relevés', function () {
    DailyRecord::create([
        'flock_id' => $this->flock->id,
        'record_date' => now()->subDays(2)->toDateString(),
        'mortality' => 20,
    ]);
    DailyRecord::create([
        'flock_id' => $this->flock->id,
        'record_date' => now()->subDays(1)->toDateString(),
        'mortality' => 5,
    ]);

    expect($this->flock->fresh()->getMortalityRate())->toBe(2.5);
});
