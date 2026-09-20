<?php

use App\Models\Contract;
use App\Models\Employee;
use App\Models\Farm;
use App\Models\JournalEntry;
use App\Models\Organisation;
use App\Models\Payroll;
use App\Models\PayrollParameter;
use App\Models\User;
use App\Services\PayrollService;
use Database\Seeders\ChartAccountSeeder;
use Database\Seeders\PayrollParameterSeeder;

/**
 * Reproduction intégrale d'un bulletin de paie ivoirien réel (juillet 2026) :
 * salaire catégoriel 135 941 (cat. 7B) + sursalaire 271 900, prime de transport 30 000,
 * 1 part, accident du travail à 3 % pour ce secteur.
 */
beforeEach(function () {
    $this->seed(PayrollParameterSeeder::class);
    $this->seed(ChartAccountSeeder::class);

    $this->organisation = Organisation::factory()->create(['cnps_employer_number' => '275897']);
    $this->farm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);

    PayrollParameter::query()->create(array_merge(
        collect(PayrollParameter::LEGAL_DEFAULTS_2024)->except('brackets')->all(),
        ['organisation_id' => $this->organisation->id, 'effective_from' => '2026-01-01', 'cnps_work_accident_rate' => 0.03],
    ))->taxBrackets()->createMany(collect(PayrollParameter::LEGAL_DEFAULTS_2024['brackets'])->map(fn (array $b, int $i) => $b + ['order' => $i])->all());

    $this->employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'farm_id' => $this->farm->id,
        'employee_number' => 'HDG-0004',
        'cnps_number' => '202600033471',
        'fiscal_parts' => 1,
        'department' => 'EXPLOITATION',
    ]);

    $this->contract = Contract::factory()->create([
        'employee_id' => $this->employee->id,
        'position' => 'DEVELOPPEUR FULLSTACK',
        'professional_category' => '7B',
        'base_salary' => 407_841,
        'category_salary' => 135_941,
        'transport_allowance' => 30_000,
    ]);
});

function lineTotal(JournalEntry $entry, string $code, string $side): float
{
    return (float) $entry->lines()->where('chart_account_code', $code)->sum($side);
}

it('retrouve exactement les montants du bulletin réel', function () {
    $payroll = app(PayrollService::class)->generatePayroll($this->employee, 2026, 7);

    expect((float) $payroll->gross_salary)->toBe(437_841.0)
        ->and((float) $payroll->taxable_gross)->toBe(407_841.0)
        ->and((float) $payroll->cnps_employee)->toBe(25_694.0)
        ->and((float) $payroll->its)->toBe(61_647.0)
        ->and((float) $payroll->cmu_employee)->toBe(500.0)
        ->and((float) $payroll->total_deductions)->toBe(87_841.0)
        ->and((float) $payroll->net_salary)->toBe(350_000.0);

    $employer = $payroll->employer_contributions;

    expect((float) $employer['family'])->toBe(4_025.0)
        ->and((float) $employer['work_accident'])->toBe(2_100.0)
        ->and((float) $employer['pension'])->toBe(31_404.0)
        ->and((float) $employer['fdfp_apprenticeship'])->toBe(1_631.0)
        ->and((float) $employer['fdfp_training'])->toBe(2_447.0)
        ->and((float) $employer['cmu'])->toBe(500.0)
        ->and((float) $payroll->cnps_employer)->toBe(37_529.0);
});

it('présente le salaire catégoriel, le sursalaire et la prime de transport non imposable sur des lignes distinctes', function () {
    $payroll = app(PayrollService::class)->generatePayroll($this->employee, 2026, 7);

    $items = $payroll->items()->orderBy('sort_order')->get();

    expect($items->pluck('label')->all())->toBe([
        'Salaire catégoriel (cat. 7B)',
        'Sursalaire',
        'Prime de transport (non imposable)',
        'Cotisation CNPS salarié (6,3 %)',
        'ITS (Impôt sur Traitements et Salaires)',
        'Couverture Maladie Universelle (part salariale)',
    ])
        ->and((float) $items[0]->amount)->toBe(135_941.0)
        ->and((float) $items[1]->amount)->toBe(271_900.0)
        ->and((float) $items[2]->amount)->toBe(30_000.0);
});

it('renseigne les mentions d\'en-tête du bulletin', function () {
    expect($this->organisation->fresh()->cnps_employer_number)->toBe('275897')
        ->and($this->contract->fresh()->professional_category)->toBe('7B')
        ->and($this->contract->fresh()->hasCategorySalarySplit())->toBeTrue()
        ->and($this->contract->fresh()->getOverSalary())->toBe(271_900.0);
});

it('comptabilise la CMU et le FDFP à la validation du bulletin, écriture équilibrée', function () {
    $user = User::factory()->create();
    $service = app(PayrollService::class);
    $payroll = $service->generatePayroll($this->employee, 2026, 7);
    $service->validatePayroll($payroll, $user->id);

    $entry = JournalEntry::query()->where('reference_type', Payroll::class)->where('reference_id', $payroll->id)->first();

    // 661 brut 437 841 ; 664 CNPS employeur 37 529 + CMU employeur 500 ; 6413 FDFP 4 078
    // 421 net 350 000 ; 431 CNPS 25 694 + 37 529 ; 433 CMU 1 000 ; 447 ITS 61 647 ; 4423 FDFP 4 078
    expect($entry)->not->toBeNull()
        ->and(lineTotal($entry, '661', 'debit'))->toBe(437_841.0)
        ->and(lineTotal($entry, '664', 'debit'))->toBe(38_029.0)
        ->and(lineTotal($entry, '6413', 'debit'))->toBe(4_078.0)
        ->and(lineTotal($entry, '421', 'credit'))->toBe(350_000.0)
        ->and(lineTotal($entry, '431', 'credit'))->toBe(63_223.0)
        ->and(lineTotal($entry, '433', 'credit'))->toBe(1_000.0)
        ->and(lineTotal($entry, '447', 'credit'))->toBe(61_647.0)
        ->and(lineTotal($entry, '4423', 'credit'))->toBe(4_078.0)
        ->and($entry->isBalanced())->toBeTrue();
});
