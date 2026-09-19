<?php

use App\Enums\StockInventoryStatus;
use App\Models\StockInventory;

test('stock inventory detects variance presence and direction', function () {
    $noDiff = new StockInventory(['difference' => 0]);
    $positive = new StockInventory(['difference' => 5]);
    $negative = new StockInventory(['difference' => -3]);

    expect($noDiff->hasDifference())->toBeFalse()
        ->and($positive->hasDifference())->toBeTrue()
        ->and($positive->isPositiveDifference())->toBeTrue()
        ->and($negative->isPositiveDifference())->toBeFalse();
});

test('stock inventory status helpers gate transitions', function () {
    $draft = new StockInventory(['status' => StockInventoryStatus::Draft]);
    $validated = new StockInventory(['status' => StockInventoryStatus::Validated]);
    $adjusted = new StockInventory(['status' => StockInventoryStatus::Adjusted]);

    expect($draft->isDraft())->toBeTrue()
        ->and($draft->canBeAdjusted())->toBeTrue()
        ->and($validated->canBeAdjusted())->toBeTrue()
        ->and($adjusted->isAdjusted())->toBeTrue()
        ->and($adjusted->canBeAdjusted())->toBeFalse();
});
