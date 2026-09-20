<?php

use App\Enums\ExpenseCategory;
use App\Enums\JournalCode;
use App\Enums\PaymentMethod;
use App\Enums\PayrollStatus;
use App\Enums\SaleStatus;
use App\Models\Account;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Organisation;
use App\Models\Payroll;
use App\Models\Sale;
use App\Models\Transaction;
use App\Services\AccountingService;
use App\Services\SaleService;
use Database\Seeders\ChartAccountSeeder;

/**
 * Journal SYSCOHADA en partie double généré depuis les événements métier.
 */
beforeEach(function () {
    $this->seed(ChartAccountSeeder::class);
    $this->organisation = Organisation::factory()->create();
    $this->farm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);
});

function lineAmount(JournalEntry $entry, string $code, string $side): float
{
    return (float) $entry->lines()->where('chart_account_code', $code)->sum($side);
}

function allEntriesBalanced(int $organisationId): bool
{
    return JournalEntry::query()->where('organisation_id', $organisationId)->get()->every(fn (JournalEntry $e) => $e->isBalanced());
}

it('une transaction de trésorerie génère exactement une écriture équilibrée dans le bon journal', function () {
    $cash = Account::factory()->cash()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'initial_balance' => 0, 'current_balance' => 0, 'is_active' => true]);

    $transaction = $cash->credit(50000, 'Apport initial', \App\Enums\TransactionCategory::Opening);

    $entries = JournalEntry::query()->where('reference_type', Transaction::class)->where('reference_id', $transaction->id)->get();

    expect($entries)->toHaveCount(1);

    $entry = $entries->first();

    expect($entry->journal)->toBe(JournalCode::Cash)
        ->and($entry->farm_id)->toBe($this->farm->id)
        ->and($entry->isBalanced())->toBeTrue()
        ->and(lineAmount($entry, '571', 'debit'))->toBe(50000.0)
        ->and(lineAmount($entry, '101', 'credit'))->toBe(50000.0)
        ->and($entry->entry_number)->toStartWith('ECR-'.now()->year.'-');

    // Rejouer explicitement ne crée pas de doublon
    app(AccountingService::class)->postTransaction($transaction);
    expect(JournalEntry::query()->where('reference_id', $transaction->id)->where('reference_type', Transaction::class)->count())->toBe(1);
});

it('une facture hors brouillon est comptabilisée 411 / 701 / 4431 avec TVA 18 %', function () {
    $sale = app(SaleService::class)->createSale(
        ['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'sale_date' => now()->toDateString(), 'status' => SaleStatus::Pending, 'tax_percent' => 18],
        [['product_name' => 'Poulets', 'quantity' => 100, 'unit_price' => 1000]],
    );

    $entry = JournalEntry::query()->where('reference_type', Sale::class)->where('reference_id', $sale->id)->where('is_reversal', false)->first();

    expect($entry)->not->toBeNull()
        ->and($entry->journal)->toBe(JournalCode::Sales)
        ->and(lineAmount($entry, '411', 'debit'))->toBe(118000.0)
        ->and(lineAmount($entry, '701', 'credit'))->toBe(100000.0)
        ->and(lineAmount($entry, '4431', 'credit'))->toBe(18000.0)
        ->and($entry->isBalanced())->toBeTrue();

    // Un seul enregistrement malgré les updates successifs de totaux
    expect(JournalEntry::query()->where('reference_type', Sale::class)->where('reference_id', $sale->id)->count())->toBe(1);

    // L'encaissement passe par la trésorerie : 571 D / 411 C
    $cash = Account::factory()->cash()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'initial_balance' => 0, 'current_balance' => 0, 'is_active' => true]);
    $sale->addPayment(118000, PaymentMethod::Cash, $cash->id);

    $paymentEntry = JournalEntry::query()->where('reference_type', Transaction::class)->latest('id')->first();

    expect(lineAmount($paymentEntry, '571', 'debit'))->toBe(118000.0)
        ->and(lineAmount($paymentEntry, '411', 'credit'))->toBe(118000.0);

    // Annulation → extourne de la facture
    $sale->cancel();

    $reversal = JournalEntry::query()->where('reference_type', Sale::class)->where('reference_id', $sale->id)->where('is_reversal', true)->first();

    expect($reversal)->not->toBeNull()
        ->and($reversal->reversed_entry_id)->toBe($entry->id)
        ->and(lineAmount($reversal, '411', 'credit'))->toBe(118000.0)
        ->and(lineAmount($reversal, '701', 'debit'))->toBe(100000.0)
        ->and(allEntriesBalanced($this->organisation->id))->toBeTrue();
});

it('une dépense TTC 118 000 à 18 % donne 100 000 de charge, 18 000 de TVA et 118 000 au fournisseur', function () {
    $expense = Expense::factory()->create([
        'organisation_id' => $this->organisation->id,
        'farm_id' => $this->farm->id,
        'category' => ExpenseCategory::Feed,
        'amount' => 118000,
        'tax_percent' => 18,
        'tax_amount' => 18000,
        'balance' => 118000,
        'expense_date' => now()->toDateString(),
    ]);

    $entry = JournalEntry::query()->where('reference_type', Expense::class)->where('reference_id', $expense->id)->first();

    expect($entry)->not->toBeNull()
        ->and($entry->journal)->toBe(JournalCode::Purchases)
        ->and(lineAmount($entry, '6021', 'debit'))->toBe(100000.0)
        ->and(lineAmount($entry, '4452', 'debit'))->toBe(18000.0)
        ->and(lineAmount($entry, '401', 'credit'))->toBe(118000.0)
        ->and($entry->isBalanced())->toBeTrue();

    // Règlement : 401 D / trésorerie C
    $bank = Account::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'type' => 'bank', 'initial_balance' => 500000, 'current_balance' => 500000, 'is_active' => true]);
    $expense->recordPayment(118000, $bank->id);

    $settlement = JournalEntry::query()->where('reference_type', Transaction::class)->latest('id')->first();

    expect($settlement->journal)->toBe(JournalCode::Bank)
        ->and(lineAmount($settlement, '401', 'debit'))->toBe(118000.0)
        ->and(lineAmount($settlement, '521', 'credit'))->toBe(118000.0)
        ->and(allEntriesBalanced($this->organisation->id))->toBeTrue();
});

it('un bulletin validé génère 661 / 664 au débit et 421 / 431 / 447 au crédit, équilibrés', function () {
    $employee = Employee::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id]);

    $payroll = Payroll::create([
        'employee_id' => $employee->id,
        'period_year' => 2026,
        'period_month' => 9,
        'gross_salary' => 300000,
        'cnps_employee' => 18900,
        'cnps_employer' => 28525,
        'its' => 23981,
        'other_deductions' => 0,
        'total_deductions' => 42881,
        'net_salary' => 257119,
        'status' => PayrollStatus::Draft,
    ]);

    expect(JournalEntry::query()->where('reference_type', Payroll::class)->count())->toBe(0);

    $payroll->update(['status' => PayrollStatus::Validated]);

    $entry = JournalEntry::query()->where('reference_type', Payroll::class)->where('reference_id', $payroll->id)->first();

    expect($entry)->not->toBeNull()
        ->and($entry->journal)->toBe(JournalCode::Payroll)
        ->and(lineAmount($entry, '661', 'debit'))->toBe(300000.0)
        ->and(lineAmount($entry, '664', 'debit'))->toBe(28525.0)
        ->and(lineAmount($entry, '421', 'credit'))->toBe(257119.0)
        ->and(lineAmount($entry, '431', 'credit'))->toBe(47425.0)
        ->and(lineAmount($entry, '447', 'credit'))->toBe(23981.0)
        ->and($entry->isBalanced())->toBeTrue()
        ->and(JournalLine::query()->where('journal_entry_id', $entry->id)->count())->toBe(5);
});

it('le rebuild rejoue l\'existant et est idempotent', function () {
    $cash = Account::factory()->cash()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'initial_balance' => 0, 'current_balance' => 0, 'is_active' => true]);
    $cash->credit(20000, 'Apport', \App\Enums\TransactionCategory::Opening);
    Expense::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'category' => ExpenseCategory::Transport, 'amount' => 5000, 'balance' => 5000, 'expense_date' => now()->toDateString()]);

    $snapshot = fn (): array => JournalLine::query()
        ->where('organisation_id', $this->organisation->id)
        ->orderBy('chart_account_code')->orderBy('debit')->orderBy('credit')
        ->get(['chart_account_code', 'debit', 'credit'])
        ->toArray();

    $before = $snapshot();

    $counts = app(AccountingService::class)->rebuild($this->organisation->id);

    expect($counts['transactions'])->toBe(1)
        ->and($counts['expenses'])->toBe(1);

    $after = $snapshot();

    expect($after)->toEqual($before)
        ->and(JournalEntry::query()->where('organisation_id', $this->organisation->id)->count())->toBe(2);

    $this->artisan('gestavi:accounting-rebuild', ['--organisation' => $this->organisation->id])->assertSuccessful();
    expect(JournalEntry::query()->where('organisation_id', $this->organisation->id)->count())->toBe(2);
});

it('refuse une écriture déséquilibrée', function () {
    expect(fn () => app(AccountingService::class)->record(
        ['organisation_id' => $this->organisation->id, 'entry_date' => now(), 'journal' => JournalCode::Miscellaneous, 'label' => 'Test'],
        [['code' => '571', 'debit' => 100], ['code' => '701', 'credit' => 90]],
    ))->toThrow(\App\Exceptions\AccountingException::class);
});
