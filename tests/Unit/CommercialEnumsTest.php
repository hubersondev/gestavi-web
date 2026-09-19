<?php

use App\Enums\CreditNoteStatus;
use App\Enums\CustomerType;
use App\Enums\PaymentMethod;
use App\Enums\QuoteStatus;
use App\Enums\SaleStatus;

test('customer type enum covers ivorian business segments', function () {
    $values = array_map(fn (CustomerType $t) => $t->value, CustomerType::cases());

    expect($values)->toBe(['particulier', 'revendeur', 'restaurant', 'hotel', 'supermarche', 'autre'])
        ->and(CustomerType::Revendeur->getLabel())->toBe('Revendeur')
        ->and(CustomerType::Hotel->getLabel())->toBe('Hôtel');
});

test('sale status enum exposes full lifecycle cases', function () {
    $values = array_map(fn (SaleStatus $s) => $s->value, SaleStatus::cases());

    expect($values)->toBe(['draft', 'pending', 'partial', 'paid', 'cancelled'])
        ->and(SaleStatus::Paid->getColor())->toBe('success')
        ->and(SaleStatus::Cancelled->getColor())->toBe('danger');
});

test('quote status enum covers quote workflow', function () {
    expect(QuoteStatus::Draft->getLabel())->toBe('Brouillon')
        ->and(QuoteStatus::Converted->getLabel())->toBe('Converti en facture')
        ->and(QuoteStatus::Expired->getColor())->toBe('warning');
});

test('payment method enum lists ivorian mobile money operators', function () {
    $values = array_map(fn (PaymentMethod $m) => $m->value, PaymentMethod::cases());

    expect($values)->toContain('orange_money', 'mtn_momo', 'wave')
        ->and(PaymentMethod::OrangeMoney->getLabel())->toBe('Orange Money')
        ->and(PaymentMethod::Cash->getLabel())->toBe('Espèces');
});

test('credit note status enum covers credit note lifecycle', function () {
    $values = array_map(fn (CreditNoteStatus $s) => $s->value, CreditNoteStatus::cases());

    expect($values)->toBe(['draft', 'issued', 'applied', 'cancelled'])
        ->and(CreditNoteStatus::Draft->getLabel())->toBe('Brouillon')
        ->and(CreditNoteStatus::Applied->getLabel())->toBe('Imputé')
        ->and(CreditNoteStatus::Applied->getColor())->toBe('success');
});
