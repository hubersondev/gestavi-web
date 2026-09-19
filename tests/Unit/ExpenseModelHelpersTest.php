<?php

use App\Enums\ExpenseStatus;
use App\Models\Expense;

test('expense status helpers detect current state', function () {
    $unpaid = new Expense(['status' => ExpenseStatus::Unpaid]);
    $partial = new Expense(['status' => ExpenseStatus::Partial]);
    $paid = new Expense(['status' => ExpenseStatus::Paid]);
    $cancelled = new Expense(['status' => ExpenseStatus::Cancelled]);

    expect($unpaid->isUnpaid())->toBeTrue()
        ->and($partial->isPartial())->toBeTrue()
        ->and($paid->isPaid())->toBeTrue()
        ->and($cancelled->isCancelled())->toBeTrue();
});

test('expense reports whether it has a remaining balance', function () {
    $open = new Expense(['balance' => 15000]);
    $cleared = new Expense(['balance' => 0]);

    expect($open->hasBalance())->toBeTrue()
        ->and($cleared->hasBalance())->toBeFalse();
});

test('expense payment progress returns correct percentage', function () {
    $expense = new Expense(['amount' => 100000, 'paid_amount' => 40000]);

    expect(round($expense->getPaymentProgress(), 2))->toBe(40.0);

    $full = new Expense(['amount' => 50000, 'paid_amount' => 50000]);
    expect($full->getPaymentProgress())->toBe(100.0);
});
