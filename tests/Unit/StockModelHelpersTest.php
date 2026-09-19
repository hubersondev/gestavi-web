<?php

use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Stock;
use App\Models\StockMovement;

test('stock reports low stock against threshold', function () {
    $low = new Stock(['quantity' => 5, 'min_quantity' => 10]);
    $ok = new Stock(['quantity' => 20, 'min_quantity' => 10]);
    $noThreshold = new Stock(['quantity' => 0, 'min_quantity' => 0]);

    expect($low->isLowStock())->toBeTrue()
        ->and($ok->isLowStock())->toBeFalse()
        ->and($noThreshold->isLowStock())->toBeFalse();
});

test('stock reports total value', function () {
    $stock = new Stock(['quantity' => 20, 'unit_price' => 1500]);

    expect($stock->getTotalValue())->toBe(30000.0);
});

test('stock movement identifies direction', function () {
    $in = new StockMovement(['type' => StockMovementType::In, 'quantity' => 10]);
    $out = new StockMovement(['type' => StockMovementType::Out, 'quantity' => 5]);

    expect($in->isIn())->toBeTrue()
        ->and($out->isOut())->toBeTrue()
        ->and($in->getSignedQuantity())->toBe(10.0)
        ->and($out->getSignedQuantity())->toBe(-5.0);
});

test('purchase order lifecycle gates transitions', function () {
    $draft = new PurchaseOrder(['status' => PurchaseOrderStatus::Draft]);
    $approved = new PurchaseOrder(['status' => PurchaseOrderStatus::Approved]);
    $received = new PurchaseOrder(['status' => PurchaseOrderStatus::Received]);
    $cancelled = new PurchaseOrder(['status' => PurchaseOrderStatus::Cancelled]);

    expect($draft->canBeApproved())->toBeTrue()
        ->and($approved->canBeReceived())->toBeTrue()
        ->and($received->canBeCancelled())->toBeFalse()
        ->and($cancelled->canBeCancelled())->toBeFalse();
});

test('purchase order item computes total with discount', function () {
    $item = new PurchaseOrderItem([
        'quantity_ordered' => 10,
        'unit_price' => 500,
        'discount_percent' => 10,
    ]);

    $item->calculateTotal();

    expect((float) $item->total)->toBe(4500.0)
        ->and((float) $item->discount_amount)->toBe(500.0);
});

test('purchase order item reports quantity pending and fully received state', function () {
    $partial = new PurchaseOrderItem(['quantity_ordered' => 10, 'quantity_received' => 4]);
    $full = new PurchaseOrderItem(['quantity_ordered' => 10, 'quantity_received' => 10]);

    expect($partial->getQuantityPending())->toBe(6.0)
        ->and($partial->isFullyReceived())->toBeFalse()
        ->and($full->isFullyReceived())->toBeTrue();
});
