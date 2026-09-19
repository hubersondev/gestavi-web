<?php

use App\Enums\StockMovementReason;
use App\Exceptions\StockException;
use App\Models\Organisation;
use App\Models\Stock;
use App\Models\Supplier;
use App\Services\StockService;

beforeEach(function () {
    $this->organisation = Organisation::factory()->create();
    $this->service = app(StockService::class);
});

it('records stock in and increments quantity', function () {
    $stock = Stock::factory()->create([
        'organisation_id' => $this->organisation->id,
        'quantity' => 100,
    ]);

    $movement = $stock->recordIn(50, StockMovementReason::Purchase);

    expect((float) $stock->fresh()->quantity)->toBe(150.0)
        ->and($movement->isIn())->toBeTrue()
        ->and((float) $movement->quantity)->toBe(50.0);
});

it('records stock out and decrements quantity', function () {
    $stock = Stock::factory()->create([
        'organisation_id' => $this->organisation->id,
        'quantity' => 100,
    ]);

    $stock->recordOut(30, StockMovementReason::Consumption);

    expect((float) $stock->fresh()->quantity)->toBe(70.0);
});

it('refuses stock out exceeding available quantity', function () {
    $stock = Stock::factory()->create([
        'organisation_id' => $this->organisation->id,
        'quantity' => 10,
    ]);

    $stock->recordOut(50, StockMovementReason::Consumption);
})->throws(StockException::class);

it('creates a purchase order with items and calculates totals', function () {
    $supplier = Supplier::factory()->create(['organisation_id' => $this->organisation->id]);

    $order = $this->service->createPurchaseOrder(
        data: [
            'organisation_id' => $this->organisation->id,
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
        ],
        items: [
            ['product_name' => 'Aliment démarrage', 'unit' => 'sac', 'quantity_ordered' => 20, 'unit_price' => 15000],
            ['product_name' => 'Vaccin Newcastle', 'unit' => 'dose', 'quantity_ordered' => 500, 'unit_price' => 50],
        ],
    );

    expect($order->items)->toHaveCount(2)
        ->and((float) $order->subtotal)->toBe(325000.0)
        ->and((float) $order->total)->toBe(325000.0);
});

it('receives a purchase order and creates stock movements', function () {
    $supplier = Supplier::factory()->create(['organisation_id' => $this->organisation->id]);
    $stock = Stock::factory()->create([
        'organisation_id' => $this->organisation->id,
        'quantity' => 0,
    ]);

    $order = $this->service->createPurchaseOrder(
        data: [
            'organisation_id' => $this->organisation->id,
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'status' => 'approved',
        ],
        items: [
            [
                'product_name' => $stock->name,
                'stock_id' => $stock->id,
                'unit' => $stock->unit,
                'quantity_ordered' => 100,
                'unit_price' => 1500,
            ],
        ],
    );

    $item = $order->items()->first();

    $order = $this->service->receivePurchaseOrder($order, [
        ['item_id' => $item->id, 'quantity' => 100],
    ]);

    expect($order->isReceived())->toBeTrue()
        ->and((float) $stock->fresh()->quantity)->toBe(100.0)
        ->and($stock->movements()->count())->toBe(1);
});
