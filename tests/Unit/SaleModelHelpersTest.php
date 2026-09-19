<?php

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\SaleItem;

test('sale helpers expose the lifecycle status', function () {
    $draft = new Sale(['status' => SaleStatus::Draft]);
    $pending = new Sale(['status' => SaleStatus::Pending]);
    $paid = new Sale(['status' => SaleStatus::Paid]);
    $cancelled = new Sale(['status' => SaleStatus::Cancelled]);

    expect($draft->isDraft())->toBeTrue()
        ->and($pending->isPending())->toBeTrue()
        ->and($paid->isPaid())->toBeTrue()
        ->and($cancelled->isCancelled())->toBeTrue();
});

test('sale reports whether it has an outstanding balance', function () {
    $paid = new Sale(['balance' => 0]);
    $unpaid = new Sale(['balance' => 5000]);

    expect($paid->hasBalance())->toBeFalse()
        ->and($unpaid->hasBalance())->toBeTrue();
});

test('sale payment progress returns percentage', function () {
    $sale = new Sale(['total' => 100000, 'paid' => 25000]);

    expect(round($sale->getPaymentProgress(), 2))->toBe(25.0);
});

test('sale item computes total with percentage discount', function () {
    $item = new SaleItem([
        'quantity' => 10,
        'unit_price' => 500,
        'discount_percent' => 10,
    ]);

    $item->calculateTotal();

    expect((float) $item->total)->toBe(4500.0)
        ->and((float) $item->discount_amount)->toBe(500.0);
});

test('sale item computes total with fixed discount amount', function () {
    $item = new SaleItem([
        'quantity' => 4,
        'unit_price' => 2500,
        'discount_percent' => 0,
        'discount_amount' => 1000,
    ]);

    $item->calculateTotal();

    expect((float) $item->total)->toBe(9000.0);
});
