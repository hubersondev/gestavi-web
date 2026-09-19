<?php

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseStatus;
use App\Exceptions\ExpenseException;
use App\Models\Account;
use App\Models\Expense;
use App\Models\Organisation;
use App\Services\ExpenseService;

beforeEach(function () {
    $this->organisation = Organisation::factory()->create();
    $this->service = app(ExpenseService::class);
});

it('records an expense without immediate payment', function () {
    $expense = $this->service->recordExpense([
        'organisation_id' => $this->organisation->id,
        'category' => ExpenseCategory::Feed,
        'description' => 'Achat aliment',
        'expense_date' => now()->toDateString(),
        'amount' => 50000,
    ]);

    expect($expense->status)->toBe(ExpenseStatus::Unpaid)
        ->and((float) $expense->balance)->toBe(50000.0)
        ->and((float) $expense->paid_amount)->toBe(0.0);
});

it('records an expense with immediate payment via account', function () {
    $account = Account::factory()->cash()->create([
        'organisation_id' => $this->organisation->id,
        'initial_balance' => 200000,
        'current_balance' => 200000,
    ]);

    $expense = $this->service->recordExpense([
        'organisation_id' => $this->organisation->id,
        'account_id' => $account->id,
        'category' => ExpenseCategory::Medicine,
        'description' => 'Achat vaccins',
        'expense_date' => now()->toDateString(),
        'amount' => 30000,
    ], payNow: true);

    expect($expense->status)->toBe(ExpenseStatus::Paid)
        ->and((float) $expense->balance)->toBe(0.0)
        ->and((float) $account->fresh()->current_balance)->toBe(170000.0);
});

it('records a partial payment on an expense', function () {
    $account = Account::factory()->cash()->create([
        'organisation_id' => $this->organisation->id,
        'initial_balance' => 100000,
        'current_balance' => 100000,
    ]);

    $expense = Expense::factory()->create([
        'organisation_id' => $this->organisation->id,
        'account_id' => $account->id,
        'amount' => 50000,
        'balance' => 50000,
    ]);

    $this->service->recordPayment($expense, 20000, $account->id);

    expect($expense->fresh()->status)->toBe(ExpenseStatus::Partial)
        ->and((float) $expense->fresh()->balance)->toBe(30000.0);
});

it('cancels an expense and reverses accounting entries', function () {
    $account = Account::factory()->cash()->create([
        'organisation_id' => $this->organisation->id,
        'initial_balance' => 200000,
        'current_balance' => 200000,
    ]);

    $expense = $this->service->recordExpense([
        'organisation_id' => $this->organisation->id,
        'account_id' => $account->id,
        'category' => ExpenseCategory::Fuel,
        'description' => 'Carburant',
        'expense_date' => now()->toDateString(),
        'amount' => 25000,
    ], payNow: true);

    $this->service->cancelExpense($expense->fresh());

    expect($expense->fresh()->status)->toBe(ExpenseStatus::Cancelled)
        ->and((float) $account->fresh()->current_balance)->toBe(200000.0);
});

it('refuses payment on a cancelled expense', function () {
    $expense = Expense::factory()->create([
        'organisation_id' => $this->organisation->id,
        'amount' => 10000,
        'balance' => 10000,
        'status' => ExpenseStatus::Cancelled,
    ]);

    $this->service->recordPayment($expense, 5000);
})->throws(ExpenseException::class);
