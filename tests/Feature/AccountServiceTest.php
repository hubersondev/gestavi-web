<?php

use App\Enums\AccountType;
use App\Enums\TransactionCategory;
use App\Exceptions\AccountException;
use App\Models\Account;
use App\Models\Organisation;
use App\Services\AccountService;

beforeEach(function () {
    $this->organisation = Organisation::factory()->create();
    $this->service = app(AccountService::class);
});

it('creates an account with auto code and opening transaction', function () {
    $account = $this->service->createAccount([
        'organisation_id' => $this->organisation->id,
        'name' => 'Caisse test',
        'type' => AccountType::Cash,
        'initial_balance' => 100000,
    ]);

    expect($account->code)->toBe('CPT-001')
        ->and($account->is_default)->toBeTrue()
        ->and((float) $account->current_balance)->toBe(100000.0)
        ->and($account->transactions()->count())->toBe(1)
        ->and($account->transactions()->first()->category)->toBe(TransactionCategory::Opening);
});

it('credits and debits an account correctly', function () {
    $account = Account::factory()->cash()->create([
        'organisation_id' => $this->organisation->id,
        'initial_balance' => 50000,
        'current_balance' => 50000,
    ]);

    $account->credit(30000, 'Dépôt', TransactionCategory::Payment);
    $account->debit(10000, 'Retrait', TransactionCategory::Expense);

    expect((float) $account->fresh()->current_balance)->toBe(70000.0);
});

it('executes a transfer between two accounts', function () {
    $from = Account::factory()->cash()->create([
        'organisation_id' => $this->organisation->id,
        'initial_balance' => 200000,
        'current_balance' => 200000,
    ]);

    $to = Account::factory()->bank()->create([
        'organisation_id' => $this->organisation->id,
        'initial_balance' => 0,
        'current_balance' => 0,
    ]);

    $transfer = $this->service->executeTransfer(
        organisationId: $this->organisation->id,
        fromAccountId: $from->id,
        toAccountId: $to->id,
        amount: 50000,
        fees: 500,
    );

    expect($transfer->isCompleted())->toBeTrue()
        ->and((float) $from->fresh()->current_balance)->toBe(149500.0)
        ->and((float) $to->fresh()->current_balance)->toBe(50000.0);
});

it('refuses a transfer with insufficient balance', function () {
    $from = Account::factory()->cash()->create([
        'organisation_id' => $this->organisation->id,
        'initial_balance' => 1000,
        'current_balance' => 1000,
    ]);

    $to = Account::factory()->bank()->create([
        'organisation_id' => $this->organisation->id,
    ]);

    $this->service->executeTransfer(
        organisationId: $this->organisation->id,
        fromAccountId: $from->id,
        toAccountId: $to->id,
        amount: 5000,
    );
})->throws(AccountException::class);

it('recalculates balance from transaction history', function () {
    $account = Account::factory()->cash()->create([
        'organisation_id' => $this->organisation->id,
        'initial_balance' => 10000,
        'current_balance' => 10000,
    ]);

    $account->credit(5000, 'Test', TransactionCategory::Other);
    $account->debit(2000, 'Test', TransactionCategory::Other);
    $account->update(['current_balance' => 999999]);
    $account->recalculateBalance();

    expect((float) $account->fresh()->current_balance)->toBe(13000.0);
});
