<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Enums\StockLotStatus;
use App\Enums\StockMovementReason;
use App\Exceptions\StockException;
use App\Models\PurchaseOrder;
use App\Models\Stock;
use App\Models\StockLot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Crée un bon de commande avec ses lignes et recalcule les totaux.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $items
     */
    public function createPurchaseOrder(array $data, array $items): PurchaseOrder
    {
        if (empty($items)) {
            throw StockException::emptyItems();
        }

        return DB::transaction(function () use ($data, $items): PurchaseOrder {
            $order = PurchaseOrder::create($data);

            foreach ($items as $index => $item) {
                $order->items()->create(array_merge($item, ['sort_order' => $index]));
            }

            $order->refresh()->calculateTotals();

            return $order->fresh(['items', 'supplier']);
        });
    }

    /**
     * Réceptionne tout ou partie des lignes d'un bon de commande.
     * Pour chaque ligne, met à jour quantity_received, crée un lot de stock si demandé,
     * et enregistre une entrée de stock avec reason=purchase.
     *
     * @param  array<int, array{item_id: int, quantity: float, create_lot?: bool, lot_number?: string, expiry_date?: string|null}>  $receptions
     */
    public function receivePurchaseOrder(PurchaseOrder $order, array $receptions): PurchaseOrder
    {
        if (! $order->canBeReceived()) {
            throw StockException::purchaseOrderNotReceivable($order->order_number);
        }

        return DB::transaction(function () use ($order, $receptions): PurchaseOrder {
            foreach ($receptions as $reception) {
                $item = $order->items()->find($reception['item_id'] ?? 0);

                if (! $item || ! $item->stock_id) {
                    continue;
                }

                $quantity = (float) ($reception['quantity'] ?? 0);

                if ($quantity <= 0) {
                    continue;
                }

                $remaining = $item->getQuantityPending();
                $actualQuantity = min($quantity, $remaining);

                if ($actualQuantity <= 0) {
                    continue;
                }

                /** @var Stock $stock */
                $stock = Stock::findOrFail($item->stock_id);

                $stockLotId = null;

                if (! empty($reception['create_lot']) && ! empty($reception['lot_number'])) {
                    $lot = StockLot::create([
                        'stock_id' => $stock->id,
                        'supplier_id' => $order->supplier_id,
                        'lot_number' => $reception['lot_number'],
                        'reception_date' => now()->toDateString(),
                        'expiry_date' => $reception['expiry_date'] ?? null,
                        'initial_quantity' => $actualQuantity,
                        'current_quantity' => $actualQuantity,
                        'unit_price' => $item->unit_price,
                        'status' => StockLotStatus::Available,
                    ]);

                    $stockLotId = $lot->id;
                }

                $stock->recordIn(
                    quantity: $actualQuantity,
                    reason: StockMovementReason::Purchase,
                    unitPrice: (float) $item->unit_price,
                    stockLotId: $stockLotId,
                    reference: $order->order_number,
                    notes: "Réception BC {$order->order_number}",
                );

                $item->update([
                    'quantity_received' => (float) $item->quantity_received + $actualQuantity,
                    'stock_lot_id' => $stockLotId ?? $item->stock_lot_id,
                    'status' => $item->getQuantityPending() <= 0 ? 'received' : 'partial',
                ]);
            }

            $order->refresh()->updateReceptionStatus();

            return $order->fresh(['items', 'supplier']);
        });
    }

    /**
     * Consomme une quantité depuis les lots d'un produit en suivant l'ordre FIFO
     * (expiration la plus proche d'abord, puis date de réception la plus ancienne).
     */
    public function consumeFromLots(Stock $stock, float $quantity, ?int $flockId = null, ?string $reference = null): Collection
    {
        if ($quantity <= 0) {
            throw StockException::invalidQuantity();
        }

        if ((float) $stock->quantity < $quantity) {
            throw StockException::insufficientStock($stock->name, $quantity, (float) $stock->quantity);
        }

        return DB::transaction(function () use ($stock, $quantity, $flockId, $reference): Collection {
            $remaining = $quantity;
            $movements = collect();

            $lots = $stock->lots()
                ->whereIn('status', [StockLotStatus::Available, StockLotStatus::Low])
                ->where('current_quantity', '>', 0)
                ->fifo()
                ->get();

            foreach ($lots as $lot) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min($remaining, (float) $lot->current_quantity);

                $movement = $stock->recordOut(
                    quantity: $take,
                    reason: StockMovementReason::Consumption,
                    flockId: $flockId,
                    reference: $reference,
                    notes: "Lot {$lot->lot_number}",
                );

                $movement->update(['stock_lot_id' => $lot->id]);

                $lot->update([
                    'current_quantity' => max(0, (float) $lot->current_quantity - $take),
                ]);

                $movements->push($movement);
                $remaining -= $take;
            }

            return $movements;
        });
    }

    /**
     * Marque un bon de commande comme envoyé au fournisseur.
     */
    public function sendPurchaseOrder(PurchaseOrder $order): void
    {
        if ($order->status !== PurchaseOrderStatus::Approved) {
            throw StockException::purchaseOrderNotReceivable($order->order_number);
        }

        $order->update(['status' => PurchaseOrderStatus::Sent]);
    }

    /**
     * Annule un bon de commande (possible tant que rien n'a été réceptionné).
     */
    public function cancelPurchaseOrder(PurchaseOrder $order): void
    {
        if (! $order->canBeCancelled()) {
            throw StockException::purchaseOrderCannotBeCancelled($order->order_number);
        }

        $order->update(['status' => PurchaseOrderStatus::Cancelled]);
    }
}
