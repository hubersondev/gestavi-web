<?php

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseStatus;
use App\Enums\PayrollStatus;
use App\Enums\TransactionCategory;
use App\Exceptions\AccountException;
use App\Models\Account;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\Farm;
use App\Models\Organisation;
use App\Models\PayrollParameter;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PayrollService;
use Database\Seeders\PayrollParameterSeeder;

beforeEach(function () {
    $this->seed(PayrollParameterSeeder::class);

    $this->organisation = Organisation::factory()->create();
    $this->farm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);
    $this->user = User::factory()->create();

    $this->employee = Employee::factory()->create([
        'organisation_id' => $this->organisation->id,
        'farm_id' => $this->farm->id,
        'fiscal_parts' => 1,
    ]);

    $this->contract = Contract::factory()->create([
        'employee_id' => $this->employee->id,
        'base_salary' => 250_000,
        'transport_allowance' => 50_000,
    ]);

    $this->service = app(PayrollService::class);
});

it('utilise le paramétrage légal seedé puis celui de l\'organisation à partir de sa date d\'effet', function () {
    $legal = $this->service->parametersFor($this->organisation->id, now());

    expect($legal->isLegalDefault())->toBeTrue()
        ->and((float) $legal->cnps_ceiling)->toBe(3_375_000.0)
        ->and($legal->taxBrackets)->toHaveCount(6);

    $own = PayrollParameter::create(array_merge(
        collect(PayrollParameter::LEGAL_DEFAULTS_2024)->except('brackets')->all(),
        ['organisation_id' => $this->organisation->id, 'effective_from' => now()->addMonth()->toDateString(), 'cnps_ceiling' => 4_000_000],
    ));

    expect($this->service->parametersFor($this->organisation->id, now())->isLegalDefault())->toBeTrue()
        ->and($this->service->parametersFor($this->organisation->id, now()->addMonths(2))->id)->toBe($own->id);
});

it('génère un bulletin depuis le contrat avec CNPS et ITS corrects', function () {
    $payroll = $this->service->generatePayroll($this->employee, 2026, 9);

    // Gains 300 000 dont transport 50 000 exonéré à hauteur de 30 000 → imposable 270 000
    // CNPS 270 000 × 6,3 % = 17 010 ; ITS 26 400 + 30 000 × 21 % = 32 700 ; CMU 500
    // Employeur : retraite 20 790 + PF 4 025 + AT 1 400 = 26 215 (CNPS) ; FDFP 1 080 + 1 620 ; CMU 500
    expect($payroll->status)->toBe(PayrollStatus::Draft)
        ->and((float) $payroll->gross_salary)->toBe(300_000.0)
        ->and((float) $payroll->taxable_gross)->toBe(270_000.0)
        ->and((float) $payroll->cnps_employee)->toBe(17_010.0)
        ->and((float) $payroll->its)->toBe(32_700.0)
        ->and((float) $payroll->cmu_employee)->toBe(500.0)
        ->and((float) $payroll->cnps_employer)->toBe(26_215.0)
        ->and((float) $payroll->employer_contributions['fdfp_apprenticeship'])->toBe(1_080.0)
        ->and((float) $payroll->employer_contributions['fdfp_training'])->toBe(1_620.0)
        ->and((float) $payroll->employer_contributions['cmu'])->toBe(500.0)
        ->and((float) $payroll->employer_contributions['total'])->toBe(29_415.0)
        ->and((float) $payroll->total_deductions)->toBe(50_210.0)
        ->and((float) $payroll->net_salary)->toBe(249_790.0)
        ->and((float) $payroll->cnps_ceiling_applied)->toBe(3_375_000.0)
        ->and($payroll->payroll_parameter_id)->not->toBeNull()
        ->and($payroll->items()->count())->toBe(5);
});

it('tient compte des parts fiscales de l\'employé', function () {
    $this->employee->update(['fiscal_parts' => 3]);

    $payroll = $this->service->generatePayroll($this->employee->fresh(), 2026, 9);

    // 32 700 − 22 000 (3 parts)
    expect((float) $payroll->its)->toBe(10_700.0)
        ->and((float) $payroll->fiscal_parts_applied)->toBe(3.0);
});

it('intègre les autres retenues dans le net', function () {
    $payroll = $this->service->generatePayroll($this->employee, 2026, 9);

    $payroll->update(['other_deductions' => 20_000]);
    $payroll->recalculate();

    expect((float) $payroll->fresh()->total_deductions)->toBe(70_210.0)
        ->and((float) $payroll->fresh()->net_salary)->toBe(229_790.0);
});

it('paye le bulletin : dépense salaires, transaction de paie et débit du compte', function () {
    $account = Account::factory()->cash()->create([
        'organisation_id' => $this->organisation->id,
        'initial_balance' => 500_000,
        'current_balance' => 500_000,
    ]);

    $payroll = $this->service->generatePayroll($this->employee, 2026, 9);
    $this->service->validatePayroll($payroll, $this->user->id);

    $this->service->markAsPaid($payroll->fresh(), $this->user->id, $account->id, now());

    $payroll = $payroll->fresh(['expense']);

    expect($payroll->status)->toBe(PayrollStatus::Paid)
        ->and($payroll->paid_by)->toBe($this->user->id)
        ->and($payroll->expense)->not->toBeNull()
        ->and($payroll->expense->category)->toBe(ExpenseCategory::Salaries)
        ->and($payroll->expense->status)->toBe(ExpenseStatus::Paid)
        ->and($payroll->expense->farm_id)->toBe($this->farm->id)
        ->and((float) $payroll->expense->amount)->toBe(249_790.0)
        ->and((float) $account->fresh()->current_balance)->toBe(250_210.0);

    $transaction = Transaction::query()->where('account_id', $account->id)->latest('id')->first();

    expect($transaction->category)->toBe(TransactionCategory::Payroll)
        ->and((float) $transaction->amount)->toBe(249_790.0);
});

it('refuse le paiement si le solde du compte est insuffisant', function () {
    $account = Account::factory()->cash()->create([
        'organisation_id' => $this->organisation->id,
        'initial_balance' => 10_000,
        'current_balance' => 10_000,
    ]);

    $payroll = $this->service->generatePayroll($this->employee, 2026, 9);
    $this->service->validatePayroll($payroll, $this->user->id);

    expect(fn () => $this->service->markAsPaid($payroll->fresh(), $this->user->id, $account->id))
        ->toThrow(AccountException::class);

    expect($payroll->fresh()->status)->toBe(PayrollStatus::Validated)
        ->and($payroll->fresh()->expense_id)->toBeNull()
        ->and((float) $account->fresh()->current_balance)->toBe(10_000.0);
});

it('refuse de payer un bulletin non validé', function () {
    $account = Account::factory()->cash()->create(['organisation_id' => $this->organisation->id]);
    $payroll = $this->service->generatePayroll($this->employee, 2026, 9);

    expect(fn () => $this->service->markAsPaid($payroll, $this->user->id, $account->id))
        ->toThrow(LogicException::class);
});
