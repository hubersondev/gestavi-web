<?php

use App\Enums\PurchaseOrderStatus;
use App\Enums\StockCategoryType;
use App\Enums\StockInventoryStatus;
use App\Enums\StockLotStatus;
use App\Enums\StockMovementReason;
use App\Enums\StockMovementType;

test('stock category type enum lists all category types', function () {
    $values = array_map(fn (StockCategoryType $t) => $t->value, StockCategoryType::cases());

    expect($values)->toBe(['feed', 'medicine', 'material', 'packaging', 'production', 'other'])
        ->and(StockCategoryType::Feed->getLabel())->toBe('Aliment')
        ->and(StockCategoryType::Medicine->getLabel())->toBe('Médicament / Vaccin');
});

test('stock lot status enum covers lifecycle', function () {
    $values = array_map(fn (StockLotStatus $s) => $s->value, StockLotStatus::cases());

    expect($values)->toBe(['available', 'low', 'depleted', 'expired', 'blocked'])
        ->and(StockLotStatus::Expired->getColor())->toBe('danger')
        ->and(StockLotStatus::Available->getLabel())->toBe('Disponible');
});

test('stock movement type enum differentiates in and out', function () {
    expect(StockMovementType::In->getLabel())->toBe('Entrée')
        ->and(StockMovementType::Out->getLabel())->toBe('Sortie')
        ->and(StockMovementType::In->getColor())->toBe('success')
        ->and(StockMovementType::Out->getColor())->toBe('danger');
});

test('stock movement reason enum exposes all business reasons', function () {
    $values = array_map(fn (StockMovementReason $r) => $r->value, StockMovementReason::cases());

    expect($values)->toContain('purchase', 'consumption', 'sale', 'loss', 'adjustment', 'transfer_in', 'transfer_out', 'return')
        ->and(StockMovementReason::Loss->getLabel())->toBe('Perte / Casse');
});

test('purchase order status enum covers full workflow', function () {
    $values = array_map(fn (PurchaseOrderStatus $s) => $s->value, PurchaseOrderStatus::cases());

    expect($values)->toBe(['draft', 'pending_approval', 'approved', 'sent', 'partially_received', 'received', 'cancelled'])
        ->and(PurchaseOrderStatus::Received->getColor())->toBe('success');
});

test('stock inventory status enum covers inventory workflow', function () {
    $values = array_map(fn (StockInventoryStatus $s) => $s->value, StockInventoryStatus::cases());

    expect($values)->toBe(['draft', 'pending_validation', 'validated', 'adjusted'])
        ->and(StockInventoryStatus::Adjusted->getColor())->toBe('success')
        ->and(StockInventoryStatus::Draft->getLabel())->toBe('Brouillon');
});
