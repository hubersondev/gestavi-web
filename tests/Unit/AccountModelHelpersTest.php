<?php

use App\Enums\AccountTransferStatus;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\AccountTransfer;

test('account helpers recognize the account type', function () {
    $cashAccount = new Account(['type' => AccountType::Cash]);
    $bankAccount = new Account(['type' => AccountType::Bank]);
    $momoAccount = new Account(['type' => AccountType::MobileMoney]);

    expect($cashAccount->isCash())->toBeTrue()
        ->and($cashAccount->isBank())->toBeFalse()
        ->and($bankAccount->isBank())->toBeTrue()
        ->and($momoAccount->isMobileMoney())->toBeTrue();
});

test('account cannot be debited if inactive or balance too low', function () {
    $inactive = new Account([
        'type' => AccountType::Cash,
        'current_balance' => 100000,
        'is_active' => false,
    ]);

    $lowBalance = new Account([
        'type' => AccountType::Cash,
        'current_balance' => 1000,
        'is_active' => true,
    ]);

    $ok = new Account([
        'type' => AccountType::Cash,
        'current_balance' => 50000,
        'is_active' => true,
    ]);

    expect($inactive->canDebit(50))->toBeFalse()
        ->and($lowBalance->canDebit(5000))->toBeFalse()
        ->and($ok->canDebit(40000))->toBeTrue();
});

test('account transfer reports total amount and state helpers', function () {
    $transfer = new AccountTransfer([
        'amount' => 50000,
        'fees' => 500,
        'status' => AccountTransferStatus::Pending,
    ]);

    expect($transfer->getTotalAmount())->toBe(50500.0)
        ->and($transfer->isPending())->toBeTrue()
        ->and($transfer->isCompleted())->toBeFalse()
        ->and($transfer->isCancelled())->toBeFalse()
        ->and($transfer->canBeCancelled())->toBeTrue();

    $completed = new AccountTransfer([
        'amount' => 1000,
        'fees' => 0,
        'status' => AccountTransferStatus::Completed,
    ]);

    expect($completed->isCompleted())->toBeTrue()
        ->and($completed->canBeCancelled())->toBeFalse();
});

