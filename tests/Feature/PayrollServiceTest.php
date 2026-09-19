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

    // Brut 300 000 → CNPS 18 900 → ITS 23 981 (cf. PayrollComputationTest)
    expect($payroll->status)->toBe(PayrollStatus::Draft)
        ->and((float) $payroll->gross_salary)->toBe(300_000.0)
        ->and((float) $payroll->cnps_employee)->toBe(18_900.0)
        ->and((float) $payroll->its)->toBe(23_981.0)
        ->and((float) $payroll->cnps_employer)->toBe(28_525.0)
        ->and((float) $payroll->total_deductions)->toBe(42_881.0)
        ->and((float) $payroll->net_salary)->toBe(257_119.0)
        ->and((float) $payroll->cnps_ceiling_applied)->toBe(3_375_000.0)
        ->and($payroll->payroll_parameter_id)->not->toBeNull()
        ->and($payroll->items()->count())->toBe(4);
});

it('tient compte des parts fiscales de l\'employé', function () {
    $this->employee->update(['fiscal_parts' => 3]);

    $payroll = $this->service->generatePayroll($this->employee->fresh(), 2026, 9);

    expect((float) $payroll->its)->toBe(1_981.0)
        ->and((float) $payroll->fiscal_parts_applied)->toBe(3.0);
});

it('intègre les autres retenues dans le net', function () {
    $payroll = $this->service->generatePayroll($this->employee, 2026, 9);

    $payroll->update(['other_deductions' => 20_000]);
    $payroll->recalculate();

    expect((float) $payroll->fresh()->total_deductions)->toBe(62_881.0)
        ->and((float) $payroll->fresh()->net_salary)->toBe(237_119.0);
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
        ->and((float) $payroll->expense->amount)->toBe(257_119.0)
        ->and((float) $account->fresh()->current_balance)->toBe(242_881.0);

    $transaction = Transaction::query()->where('account_id', $account->id)->latest('id')->first();

    expect($transaction->category)->toBe(TransactionCategory::Payroll)
        ->and((float) $transaction->amount)->toBe(257_119.0);
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
