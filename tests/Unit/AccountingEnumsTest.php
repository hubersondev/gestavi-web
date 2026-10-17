<?php

use App\Enums\AccountTransferStatus;
use App\Enums\AccountType;
use App\Enums\MobileMoneyOperator;
use App\Enums\TransactionCategory;
use App\Enums\TransactionType;

test('account type enum has french labels and cases', function () {
    $values = array_map(fn (AccountType $type) => $type->value, AccountType::cases());

    expect($values)->toBe(['cash', 'bank', 'mobile_money', 'other'])
        ->and(AccountType::Cash->getLabel())->toBe('Caisse')
        ->and(AccountType::Bank->getLabel())->toBe('Banque')
        ->and(AccountType::MobileMoney->getLabel())->toBe('Mobile Money')
        ->and(AccountType::Bank->getIcon())->toBe('heroicon-o-building-library');
});

test('mobile money operator enum maps ivorian operators', function () {
    $values = array_map(fn (MobileMoneyOperator $op) => $op->value, MobileMoneyOperator::cases());

    expect($values)->toBe(['orange', 'mtn', 'moov', 'wave'])
        ->and(MobileMoneyOperator::Orange->getLabel())->toBe('Orange Money');
});

test('transaction type enum exposes credit and debit', function () {
    expect(TransactionType::Credit->getLabel())->toBe('Crédit')
        ->and(TransactionType::Debit->getLabel())->toBe('Débit')
        ->and(TransactionType::Credit->getColor())->toBe('success')
        ->and(TransactionType::Debit->getColor())->toBe('danger');
});

test('transaction category enum lists accounting categories', function () {
    $values = array_map(fn (TransactionCategory $c) => $c->value, TransactionCategory::cases());

    expect($values)->toBe([
        'opening', 'sale', 'payment', 'expense',
        'transfer_in', 'transfer_out', 'adjustment',
        'payroll', 'refund', 'theft', 'contribution', 'withdrawal', 'other',
    ]);
});

test('account transfer status enum cases', function () {
    expect(AccountTransferStatus::Pending->getLabel())->toBe('En attente')
        ->and(AccountTransferStatus::Completed->getLabel())->toBe('Complété')
        ->and(AccountTransferStatus::Cancelled->getLabel())->toBe('Annulé');
});
