<?php

use App\Enums\ProductionKind;
use App\Models\ChickenType;
use App\Models\DailyRecord;
use App\Models\Flock;
use App\Services\FlockService;

/**
 * Indicateurs zootechniques : formules vérifiées à la main.
 */
function makeFlock(ProductionKind $kind, int $started = 1000, int $ageDays = 35): Flock
{
    $type = ChickenType::factory()->create(['production_kind' => $kind->value]);

    return Flock::factory()->active()->create([
        'chicken_type_id' => $type->id,
        'quantity_started' => $started,
        'quantity_current' => $started,
        'start_date' => now()->subDays($ageDays),
        'age_at_arrival' => 0,
    ]);
}

it('calcule IC, GMQ, IPE et taux de mortalité pour une bande de chair', function () {
    $flock = makeFlock(ProductionKind::Broiler);

    // 30 morts au total, 4 000 kg d'aliment, poids final 2 000 g à J35
    DailyRecord::create(['flock_id' => $flock->id, 'record_date' => now()->subDays(30)->toDateString(), 'age_days' => 5, 'mortality' => 20, 'feed_consumed_kg' => 1000]);
    DailyRecord::create(['flock_id' => $flock->id, 'record_date' => now()->subDays(15)->toDateString(), 'age_days' => 20, 'mortality' => 10, 'feed_consumed_kg' => 1500]);
    DailyRecord::create(['flock_id' => $flock->id, 'record_date' => now()->toDateString(), 'age_days' => 35, 'feed_consumed_kg' => 1500, 'average_weight_g' => 2000]);

    $indicators = app(FlockService::class)->calculatePerformanceIndicators($flock->fresh());

    // Poids vif produit = 2 000 g × 970 présents − 40 g × 1 000 = 1 900 kg ; IC = 4 000 / 1 900 = 2,11
    // GMQ = (2 000 − 40) / 35 = 56 g/j
    // Viabilité = 97 % ; IPE = 97 × 2,0 / (35 × 2,11) × 100 = 262,7
    expect($indicators['taux_mortalite'])->toBe(3.0)
        ->and($indicators['viabilite'])->toBe(97.0)
        ->and($indicators['ic'])->toBe(2.11)
        ->and($indicators['gmq'])->toBe(56.0)
        ->and($indicators['ipe'])->toBe(262.7)
        ->and($indicators['aliment_total_kg'])->toBe(4000.0)
        ->and($indicators)->not->toHaveKey('taux_ponte_moyen');
});

it('utilise la pesée de départ quand elle existe (J0–J3)', function () {
    $flock = makeFlock(ProductionKind::Broiler);

    DailyRecord::create(['flock_id' => $flock->id, 'record_date' => now()->subDays(34)->toDateString(), 'age_days' => 1, 'average_weight_g' => 45]);
    DailyRecord::create(['flock_id' => $flock->id, 'record_date' => now()->toDateString(), 'age_days' => 35, 'average_weight_g' => 2085]);

    $indicators = app(FlockService::class)->calculatePerformanceIndicators($flock->fresh());

    // (2 085 − 45) / (35 − 1) = 60 g/j
    expect($indicators['gmq'])->toBe(60.0);
});

it('calcule le taux de ponte moyen et les œufs par poule logée', function () {
    $flock = makeFlock(ProductionKind::Layer, started: 500, ageDays: 200);

    // Jour 1 : 450 œufs pour 500 poules ; jour 2 : 10 morts puis 392 œufs pour 490 ; jour 3 : 0 œuf (inclus)
    DailyRecord::create(['flock_id' => $flock->id, 'record_date' => now()->subDays(3)->toDateString(), 'eggs_collected' => 450]);
    DailyRecord::create(['flock_id' => $flock->id, 'record_date' => now()->subDays(2)->toDateString(), 'mortality' => 10, 'eggs_collected' => 392]);
    DailyRecord::create(['flock_id' => $flock->id, 'record_date' => now()->subDay()->toDateString(), 'eggs_collected' => 0]);

    $indicators = app(FlockService::class)->calculatePerformanceIndicators($flock->fresh());

    // Σ œufs = 842 ; Σ poules-jours = 500 + 490 + 490 = 1 480 → 56,89 %
    // HHP = 842 / 500 = 1,7
    expect($indicators['taux_ponte_moyen'])->toBe(56.89)
        ->and($indicators['oeufs_par_poule_logee'])->toBe(1.7)
        ->and($indicators['oeufs_total'])->toBe(842)
        ->and($indicators)->not->toHaveKey('ic');
});

it('retourne null quand les données manquent au lieu de diviser par zéro', function () {
    $flock = makeFlock(ProductionKind::Broiler);

    $indicators = app(FlockService::class)->calculatePerformanceIndicators($flock);

    expect($indicators['ic'])->toBeNull()
        ->and($indicators['gmq'])->toBeNull()
        ->and($indicators['ipe'])->toBeNull()
        ->and($indicators['taux_mortalite'])->toBe(0.0);
});
