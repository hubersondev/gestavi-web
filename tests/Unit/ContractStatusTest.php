<?php

use App\Enums\ContractStatus;

test('contract status draft case exists with french label', function () {
    expect(ContractStatus::Draft->value)->toBe('draft')
        ->and(ContractStatus::Draft->getLabel())->toBe('Brouillon')
        ->and(ContractStatus::Draft->color())->toBe('warning');
});

test('contract status enum exposes all expected cases', function () {
    $values = array_map(fn (ContractStatus $status) => $status->value, ContractStatus::cases());

    expect($values)->toBe(['draft', 'active', 'expired', 'renewed', 'cancelled']);
});
