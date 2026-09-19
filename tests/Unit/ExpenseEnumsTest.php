<?php

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseStatus;

test('expense category enum exposes all farm cost categories', function () {
    $values = array_map(fn (ExpenseCategory $c) => $c->value, ExpenseCategory::cases());

    expect($values)->toContain('feed', 'medicine', 'fuel', 'utilities', 'rent', 'maintenance', 'salaries')
        ->and(ExpenseCategory::Feed->getLabel())->toBe('Aliment')
        ->and(ExpenseCategory::Medicine->getLabel())->toBe('Médicaments / Vaccins')
        ->and(ExpenseCategory::Salaries->getLabel())->toBe('Salaires')
        ->and(ExpenseCategory::Fuel->getIcon())->toBe('heroicon-o-fire');
});

test('expense status enum covers payment lifecycle', function () {
    $values = array_map(fn (ExpenseStatus $s) => $s->value, ExpenseStatus::cases());

    expect($values)->toBe(['unpaid', 'partial', 'paid', 'cancelled'])
        ->and(ExpenseStatus::Paid->getColor())->toBe('success')
        ->and(ExpenseStatus::Unpaid->getLabel())->toBe('À payer')
        ->and(ExpenseStatus::Partial->getLabel())->toBe('Partiellement payée');
});
