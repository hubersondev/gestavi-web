<?php

use App\Models\PayrollParameter;
use App\Services\PayrollService;

// Le cast « date » du modèle a besoin du conteneur (format de date de la connexion) ; aucune requête n'est émise.
uses(Tests\TestCase::class);

/**
 * Calculs de paie purs (aucune base de données) sur le paramétrage légal embarqué.
 *
 * Barème légal 2024, recoupé sur un bulletin réel de juillet 2026 :
 *  - CNPS salarié 6,3 % sur brut imposable plafonné à 3 375 000 FCFA
 *  - ITS : barème appliqué directement au brut imposable ; tranches 0 % ≤ 75 000,
 *    16 % ≤ 240 000, 21 % ≤ 800 000, 24 % ≤ 2 400 000, 28 % ≤ 8 000 000, 32 % au-delà ;
 *    réduction pour charges de famille selon les parts (3 parts = 22 000 FCFA)
 *  - prime de transport exonérée jusqu'à 30 000 FCFA ; CMU 500 + 500 ; FDFP 0,4 % + 0,6 %.
 */
beforeEach(function () {
    $this->params = PayrollParameter::legalDefaults();
    $this->service = new PayrollService;
});

it('calcule la CNPS salarié à 6,3 % du brut imposable', function (float $gross, float $expected) {
    expect($this->service->calculateCnpsEmployee($gross, $this->params))->toBe($expected);
})->with([
    'brut 150 000' => [150_000.0, 9_450.0],
    'brut 300 000' => [300_000.0, 18_900.0],
    'brut 4 000 000 plafonné à 3 375 000' => [4_000_000.0, 212_625.0],
]);

it('calcule les charges patronales par branche avec leurs plafonds, FDFP et CMU', function () {
    // 150 000 × 7,7 % = 11 550 ; 70 000 × 5,75 % = 4 025 ; 70 000 × 2 % = 1 400
    expect($this->service->calculateCnpsEmployer(150_000, $this->params))->toBe(16_975.0);

    $breakdown = $this->service->employerContributionsBreakdown(150_000, $this->params);

    expect($breakdown)->toBe([
        'pension' => 11_550.0,
        'family' => 4_025.0,
        'work_accident' => 1_400.0,
        'fdfp_apprenticeship' => 600.0,
        'fdfp_training' => 900.0,
        'cmu' => 500.0,
        'total' => 18_975.0,
    ]);
});

it('calcule l\'ITS d\'un brut imposable de 300 000 FCFA pour 1 part', function () {
    // Tranche 75 000–240 000 : 165 000 × 16 % = 26 400 ; tranche 240 000–300 000 : 60 000 × 21 % = 12 600
    expect($this->service->calculateIts(300_000, $this->params, 1.0))->toBe(39_000.0);
});

it('reproduit le bulletin réel de juillet 2026', function () {
    // Brut imposable 407 841 (catégoriel 135 941 + sursalaire 271 900), transport 30 000 exonéré
    $computed = $this->service->computeFromAmounts(407_841, 30_000, 0, 0, $this->params, 1.0);

    expect($computed['gross'])->toBe(437_841.0)
        ->and($computed['taxable_gross'])->toBe(407_841.0)
        ->and($computed['exempt_transport'])->toBe(30_000.0)
        ->and($computed['cnps_employee'])->toBe(25_694.0)
        ->and($computed['its'])->toBe(61_647.0)
        ->and($computed['cmu_employee'])->toBe(500.0)
        ->and($computed['total_deductions'])->toBe(87_841.0)
        ->and($computed['net'])->toBe(350_000.0);
});

it('n\'exonère la prime de transport que jusqu\'au plafond', function () {
    $computed = $this->service->computeFromAmounts(200_000, 50_000, 0, 0, $this->params, 1.0);

    expect($computed['exempt_transport'])->toBe(30_000.0)
        ->and($computed['taxable_gross'])->toBe(220_000.0)
        ->and($computed['gross'])->toBe(250_000.0);
});

it('n\'applique aucun ITS sous 75 000 FCFA de brut imposable', function () {
    expect($this->service->calculateIts(70_000, $this->params, 1.0))->toBe(0.0);
});

it('applique la réduction pour charges de famille sans passer sous zéro', function () {
    // 3 parts : 39 000 − 22 000 = 17 000
    expect($this->service->calculateIts(300_000, $this->params, 3.0))->toBe(17_000.0);

    // 5 parts : 39 000 − 44 000 < 0 → 0
    expect($this->service->calculateIts(300_000, $this->params, 5.0))->toBe(0.0);
});

it('traverse toutes les tranches pour un haut salaire', function () {
    // (240 000−75 000)×16 % = 26 400 + (800 000−240 000)×21 % = 117 600
    // + (2 400 000−800 000)×24 % = 384 000 + (5 000 000−2 400 000)×28 % = 728 000 → 1 256 000
    expect($this->service->calculateIts(5_000_000, $this->params, 1.0))->toBe(1_256_000.0);
});

it('retrouve le brut à partir du net cible', function () {
    $gross = $this->service->grossFromNet(200_000, $this->params, 1.0);
    $net = $this->service->computeFromAmounts($gross, 0, 0, 0, $this->params, 1.0)['net'];

    expect(abs($net - 200_000))->toBeLessThan(2.0);

    // Bulletin réel hors prime de transport : brut imposable 407 841 → net 320 000 (350 000 − 30 000)
    expect(abs($this->service->grossFromNet(320_000, $this->params, 1.0) - 407_841))->toBeLessThanOrEqual(2.0);
});
