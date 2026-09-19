<?php

use App\Models\PayrollParameter;
use App\Services\PayrollService;

// Le cast « date » du modèle a besoin du conteneur (format de date de la connexion) ; aucune requête n'est émise.
uses(Tests\TestCase::class);

/**
 * Calculs de paie purs (aucune base de données) sur le paramétrage légal embarqué.
 *
 * Barème légal 2024 (à valider par un expert-comptable) :
 *  - CNPS salarié 6,3 % sur brut plafonné à 3 375 000 FCFA
 *  - ITS : assiette = (brut − CNPS) × 80 % ; tranches 0 % ≤ 75 000, 16 % ≤ 240 000,
 *    21 % ≤ 800 000, 24 % ≤ 2 400 000, 28 % ≤ 8 000 000, 32 % au-delà ;
 *    réduction pour charges de famille selon les parts (3 parts = 22 000 FCFA).
 */
beforeEach(function () {
    $this->params = PayrollParameter::legalDefaults();
    $this->service = new PayrollService;
});

it('calcule la CNPS salarié à 6,3 % du brut', function (float $gross, float $expected) {
    expect($this->service->calculateCnpsEmployee($gross, $this->params))->toBe($expected);
})->with([
    'brut 150 000' => [150_000.0, 9_450.0],
    'brut 300 000' => [300_000.0, 18_900.0],
    'brut 4 000 000 plafonné à 3 375 000' => [4_000_000.0, 212_625.0],
]);

it('calcule les charges patronales par branche avec leurs plafonds', function () {
    // 150 000 × 7,7 % = 11 550 ; 70 000 × 5,75 % = 4 025 ; 70 000 × 2 % = 1 400
    expect($this->service->calculateCnpsEmployer(150_000, $this->params))->toBe(16_975.0);

    $breakdown = $this->service->employerContributionsBreakdown(150_000, $this->params);

    expect($breakdown)->toBe(['pension' => 11_550.0, 'family' => 4_025.0, 'work_accident' => 1_400.0]);
});

it('calcule l\'ITS d\'un brut de 300 000 FCFA pour 1 part', function () {
    // CNPS = 300 000 × 6,3 % = 18 900 → base 281 100 → assiette 281 100 × 80 % = 224 880
    // Tranche 0–75 000 : 0 ; tranche 75 000–224 880 : 149 880 × 16 % = 23 980,80 → 23 981 FCFA
    $cnps = $this->service->calculateCnpsEmployee(300_000, $this->params);

    expect($cnps)->toBe(18_900.0)
        ->and($this->service->calculateIts(300_000 - $cnps, $this->params, 1.0))->toBe(23_981.0);
});

it('n\'applique aucun ITS sous 75 000 FCFA d\'assiette', function () {
    // brut 90 000 → CNPS 5 670 → base 84 330 → assiette 67 464 < 75 000
    $cnps = $this->service->calculateCnpsEmployee(90_000, $this->params);

    expect($this->service->calculateIts(90_000 - $cnps, $this->params, 1.0))->toBe(0.0);
});

it('applique la réduction pour charges de famille sans passer sous zéro', function () {
    $cnps = $this->service->calculateCnpsEmployee(300_000, $this->params);
    $base = 300_000 - $cnps;

    // 3 parts : 23 981 − 22 000 = 1 981
    expect($this->service->calculateIts($base, $this->params, 3.0))->toBe(1_981.0);

    // 5 parts : 23 981 − 44 000 < 0 → 0
    expect($this->service->calculateIts($base, $this->params, 5.0))->toBe(0.0);
});

it('traverse toutes les tranches pour un haut salaire', function () {
    // brut 5 000 000 → CNPS plafonnée 212 625 → base 4 787 375 → assiette 3 829 900
    // 0 + (240 000−75 000)×16 % = 26 400 + (800 000−240 000)×21 % = 117 600
    // + (2 400 000−800 000)×24 % = 384 000 + (3 829 900−2 400 000)×28 % = 400 372 → 928 372
    $cnps = $this->service->calculateCnpsEmployee(5_000_000, $this->params);

    expect($this->service->calculateIts(5_000_000 - $cnps, $this->params, 1.0))->toBe(928_372.0);
});

it('retrouve le brut à partir du net cible', function () {
    $gross = $this->service->grossFromNet(200_000, $this->params, 1.0);
    $cnps = $this->service->calculateCnpsEmployee($gross, $this->params);
    $its = $this->service->calculateIts($gross - $cnps, $this->params, 1.0);

    expect(abs(($gross - $cnps - $its) - 200_000))->toBeLessThan(2.0);
});
