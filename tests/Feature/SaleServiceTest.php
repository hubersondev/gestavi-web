<?php

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Exceptions\CommercialException;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Organisation;
use App\Models\Sale;
use App\Services\SaleService;

beforeEach(function () {
    $this->organisation = Organisation::factory()->create();
    $this->service = app(SaleService::class);
});

it('creates a sale with items and calculates totals', function () {
    $sale = $this->service->createSale(
        data: [
            'organisation_id' => $this->organisation->id,
            'sale_date' => now()->toDateString(),
        ],
        items: [
            ['product_name' => 'Poulets vifs', 'unit' => 'kg', 'quantity' => 50, 'unit_price' => 2500],
            ['product_name' => 'Oeufs', 'unit' => 'plateau', 'quantity' => 10, 'unit_price' => 3000],
        ],
    );

    expect($sale->items)->toHaveCount(2)
        ->and((float) $sale->subtotal)->toBe(155000.0)
        ->and((float) $sale->total)->toBe(155000.0)
        ->and($sale->status)->toBe(SaleStatus::Pending);
});

it('records a payment and updates sale status', function () {
    $account = Account::factory()->cash()->create([
        'organisation_id' => $this->organisation->id,
        'initial_balance' => 0,
        'current_balance' => 0,
    ]);

    $sale = $this->service->createSale(
        data: [
            'organisation_id' => $this->organisation->id,
            'sale_date' => now()->toDateString(),
            'account_id' => $account->id,
        ],
        items: [
            ['product_name' => 'Poulets', 'unit' => 'unité', 'quantity' => 20, 'unit_price' => 3500],
        ],
    );

    $this->service->recordPayment(
        sale: $sale->fresh(),
        amount: 70000,
        method: PaymentMethod::Cash,
        accountId: $account->id,
    );

    expect($sale->fresh()->status)->toBe(SaleStatus::Paid)
        ->and((float) $sale->fresh()->balance)->toBe(0.0)
        ->and((float) $account->fresh()->current_balance)->toBe(70000.0);
});

it('refuses payment exceeding balance', function () {
    $sale = $this->service->createSale(
        data: [
            'organisation_id' => $this->organisation->id,
            'sale_date' => now()->toDateString(),
        ],
        items: [
            ['product_name' => 'Test', 'unit' => 'u', 'quantity' => 1, 'unit_price' => 1000],
        ],
    );

    $this->service->recordPayment($sale->fresh(), 50000, PaymentMethod::Cash);
})->throws(CommercialException::class);

it('cancels a sale and reverses accounting entries', function () {
    $account = Account::factory()->cash()->create([
        'organisation_id' => $this->organisation->id,
        'initial_balance' => 0,
        'current_balance' => 0,
    ]);

    $sale = $this->service->createSale(
        data: [
            'organisation_id' => $this->organisation->id,
            'sale_date' => now()->toDateString(),
            'account_id' => $account->id,
        ],
        items: [
            ['product_name' => 'Poulets', 'unit' => 'u', 'quantity' => 10, 'unit_price' => 2000],
        ],
    );

    $this->service->recordPayment($sale->fresh(), 20000, PaymentMethod::Cash, $account->id);
    $this->service->cancelSale($sale->fresh());

    expect($sale->fresh()->status)->toBe(SaleStatus::Cancelled)
        ->and((float) $account->fresh()->current_balance)->toBe(0.0);
});

it('converts an accepted quote to a sale', function () {
    $quote = \App\Models\Quote::factory()->accepted()->create([
        'organisation_id' => $this->organisation->id,
        'quote_date' => now()->toDateString(),
        'discount_percent' => 5,
    ]);

    $quote->items()->create([
        'product_name' => 'Poulets vivants',
        'unit' => 'kg',
        'quantity' => 100,
        'unit_price' => 2500,
        'total' => 250000,
    ]);
    $quote->refresh()->calculateTotals();

    $sale = $this->service->convertQuoteToSale($quote->fresh());

    expect($sale->items)->toHaveCount(1)
        ->and($sale->quote_id)->toBe($quote->id)
        ->and($quote->fresh()->status)->toBe(\App\Enums\QuoteStatus::Converted)
        ->and($quote->fresh()->converted_sale_id)->toBe($sale->id);
});
