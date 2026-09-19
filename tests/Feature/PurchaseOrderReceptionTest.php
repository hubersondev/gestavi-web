<?php

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\CommercialException;
use App\Exceptions\StockException;
use App\Models\Farm;
use App\Models\Organisation;
use App\Models\Stock;
use App\Models\Supplier;
use App\Services\StockService;

/**
 * Réception d'un bon de commande ligne par ligne : lot, péremption, prix réel,
 * réception partielle, refus de sur-réception, verrouillage des lignes.
 */
beforeEach(function () {
    $this->organisation = Organisation::factory()->create();
    $farm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);
    $this->supplier = Supplier::factory()->create(['farm_id' => $farm->id]);
    $this->service = app(StockService::class);

    $this->feed = Stock::factory()->create(['organisation_id' => $this->organisation->id, 'quantity' => 0, 'unit_price' => 0, 'unit' => 'sac']);
    $this->vaccine = Stock::factory()->create(['organisation_id' => $this->organisation->id, 'quantity' => 0, 'unit_price' => 0, 'unit' => 'dose']);

    $this->order = $this->service->createPurchaseOrder(
        data: [
            'organisation_id' => $this->organisation->id,
            'supplier_id' => $this->supplier->id,
            'order_date' => now()->toDateString(),
            'status' => PurchaseOrderStatus::Approved->value,
        ],
        items: [
            ['product_name' => 'Aliment démarrage', 'stock_id' => $this->feed->id, 'unit' => 'sac', 'quantity_ordered' => 100, 'unit_price' => 15000],
            ['product_name' => 'Vaccin Newcastle', 'stock_id' => $this->vaccine->id, 'unit' => 'dose', 'quantity_ordered' => 2000, 'unit_price' => 25],
        ],
    );
});

it('réceptionne partiellement une ligne avec lot, péremption et prix réel', function () {
    $feedItem = $this->order->items()->where('stock_id', $this->feed->id)->first();

    $order = $this->service->receivePurchaseOrder($this->order, [
        [
            'item_id' => $feedItem->id,
            'quantity' => 40,
            'lot_number' => 'FAB-2026-09-A',
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'unit_price' => 15500,
        ],
    ]);

    $lot = $this->feed->lots()->first();

    expect($order->status)->toBe(PurchaseOrderStatus::PartiallyReceived)
        ->and($feedItem->fresh()->status)->toBe('partial')
        ->and((float) $feedItem->fresh()->quantity_received)->toBe(40.0)
        ->and($lot)->not->toBeNull()
        ->and($lot->lot_number)->toBe('FAB-2026-09-A')
        ->and((float) $lot->current_quantity)->toBe(40.0)
        ->and((float) $lot->unit_price)->toBe(15500.0)
        ->and($lot->expiry_date->toDateString())->toBe(now()->addMonths(6)->toDateString())
        ->and((float) $this->feed->fresh()->quantity)->toBe(40.0)
        // Stock vide avant l'entrée : le CMUP prend le prix réel de réception
        ->and((float) $this->feed->fresh()->unit_price)->toBe(15500.0)
        ->and($this->feed->movements()->where('stock_lot_id', $lot->id)->count())->toBe(1);
});

it('refuse une quantité reçue supérieure au reste à recevoir', function () {
    $feedItem = $this->order->items()->where('stock_id', $this->feed->id)->first();

    $this->service->receivePurchaseOrder($this->order, [['item_id' => $feedItem->id, 'quantity' => 40]]);

    expect(fn () => $this->service->receivePurchaseOrder($this->order->fresh(), [
        ['item_id' => $feedItem->id, 'quantity' => 70],
    ]))->toThrow(StockException::class);

    expect((float) $this->feed->fresh()->quantity)->toBe(40.0);
});

it('clôture le bon de commande quand toutes les lignes sont reçues', function () {
    $feedItem = $this->order->items()->where('stock_id', $this->feed->id)->first();
    $vaccineItem = $this->order->items()->where('stock_id', $this->vaccine->id)->first();

    $this->service->receivePurchaseOrder($this->order, [['item_id' => $feedItem->id, 'quantity' => 40]]);
    $order = $this->service->receivePurchaseOrder($this->order->fresh(), [
        ['item_id' => $feedItem->id, 'quantity' => 60],
        ['item_id' => $vaccineItem->id, 'quantity' => 2000, 'lot_number' => 'VAX-77', 'expiry_date' => now()->addYear()->toDateString()],
    ]);

    expect($order->status)->toBe(PurchaseOrderStatus::Received)
        ->and($order->isReceived())->toBeTrue()
        ->and((float) $this->feed->fresh()->quantity)->toBe(100.0)
        ->and((float) $this->vaccine->fresh()->quantity)->toBe(2000.0)
        ->and($this->vaccine->lots()->count())->toBe(1);
});

it('interdit de modifier les lignes après une réception', function () {
    $feedItem = $this->order->items()->where('stock_id', $this->feed->id)->first();

    $this->service->syncPurchaseOrderItems($this->order, [
        ['id' => $feedItem->id, 'stock_id' => $this->feed->id, 'product_name' => 'Aliment démarrage', 'unit' => 'sac', 'quantity_ordered' => 120, 'unit_price' => 15000],
    ]);

    $this->order->refresh();

    expect($this->order->items()->count())->toBe(1)
        ->and((float) $this->order->total)->toBe(1800000.0);

    $this->service->receivePurchaseOrder($this->order, [['item_id' => $feedItem->id, 'quantity' => 10]]);

    expect(fn () => $this->service->syncPurchaseOrderItems($this->order->fresh(), [
        ['id' => $feedItem->id, 'stock_id' => $this->feed->id, 'product_name' => 'Aliment démarrage', 'unit' => 'sac', 'quantity_ordered' => 5, 'unit_price' => 15000],
    ]))->toThrow(CommercialException::class);
});
