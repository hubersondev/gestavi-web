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
     * Remplace les lignes d'un bon de commande (édition). Interdit dès qu'une réception a eu lieu.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function syncPurchaseOrderItems(PurchaseOrder $order, array $items): PurchaseOrder
    {
        if ($order->items()->where('quantity_received', '>', 0)->exists()) {
            throw \App\Exceptions\CommercialException::purchaseOrderLocked($order->order_number);
        }

        if (empty($items)) {
            throw StockException::emptyItems();
        }

        $columns = ['stock_id', 'product_name', 'description', 'unit', 'quantity_ordered', 'unit_price'];

        return DB::transaction(function () use ($order, $items, $columns): PurchaseOrder {
            $keptIds = [];

            foreach (array_values($items) as $index => $item) {
                $attributes = array_merge(array_intersect_key($item, array_flip($columns)), ['sort_order' => $index]);
                $existing = ! empty($item['id']) ? $order->items()->find($item['id']) : null;

                if ($existing) {
                    $existing->update($attributes);
                    $keptIds[] = $existing->getKey();
                } else {
                    $keptIds[] = $order->items()->create($attributes)->getKey();
                }
            }

            $order->items()->whereNotIn('id', $keptIds)->get()->each->delete();

            $order->refresh()->calculateTotals();

            return $order->fresh(['items', 'supplier']);
        });
    }

    /**
     * Réceptionne tout ou partie des lignes d'un bon de commande, ligne par ligne.
     * Pour chaque ligne : quantité reçue (refusée si supérieure au reste à recevoir),
     * lot créé dès qu'un numéro de lot est fourni (péremption, prix réel), entrée de stock
     * valorisée au prix réel (CMUP) et rattachée au lot.
     *
     * @param  array<int, array{item_id: int, quantity: float, lot_number?: string|null, expiry_date?: string|null, unit_price?: float|null, create_lot?: bool}>  $receptions
     */
    public function receivePurchaseOrder(PurchaseOrder $order, array $receptions): PurchaseOrder
    {
        if (! $order->canBeReceived()) {
            throw StockException::purchaseOrderNotReceivable($order->order_number);
        }

        return DB::transaction(function () use ($order, $receptions): PurchaseOrder {
            foreach ($receptions as $reception) {
                $item = $order->items()->lockForUpdate()->find($reception['item_id'] ?? 0);

                if (! $item || ! $item->stock_id) {
                    continue;
                }

                $quantity = (float) ($reception['quantity'] ?? 0);

                if ($quantity <= 0) {
                    continue;
                }

                $remaining = $item->getQuantityPending();

                if ($quantity > $remaining + 0.001) {
                    throw StockException::receptionExceedsOrdered($item->product_name, $quantity, $remaining);
                }

                /** @var Stock $stock */
                $stock = Stock::findOrFail($item->stock_id);

                $unitPrice = isset($reception['unit_price']) && $reception['unit_price'] !== '' && $reception['unit_price'] !== null
                    ? (float) $reception['unit_price']
                    : (float) $item->unit_price;

                $stockLotId = null;

                if (! empty($reception['lot_number'])) {
                    $lot = StockLot::create([
                        'stock_id' => $stock->id,
                        'supplier_id' => $order->supplier_id,
                        'lot_number' => $reception['lot_number'],
                        'reception_date' => now()->toDateString(),
                        'expiry_date' => $reception['expiry_date'] ?? null,
                        'initial_quantity' => $quantity,
                        'current_quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'status' => StockLotStatus::Available,
                    ]);

                    $stockLotId = $lot->id;
                }

                $stock->recordIn(
                    quantity: $quantity,
                    reason: StockMovementReason::Purchase,
                    unitPrice: $unitPrice,
                    stockLotId: $stockLotId,
                    reference: $order->order_number,
                    notes: "Réception BC {$order->order_number}".($stockLotId ? " — lot {$reception['lot_number']}" : ''),
                );

                $item->update([
                    'quantity_received' => (float) $item->quantity_received + $quantity,
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
    public function consumeFromLots(
        Stock $stock,
        float $quantity,
        ?int $flockId = null,
        ?string $reference = null,
        StockMovementReason $reason = StockMovementReason::Consumption,
        ?string $notes = null,
    ): Collection {
        if ($quantity <= 0) {
            throw StockException::invalidQuantity();
        }

        return DB::transaction(function () use ($stock, $quantity, $flockId, $reference, $reason, $notes): Collection {
            // Produit et lots verrouillés : la disponibilité est relue en base
            $stock = Stock::query()->lockForUpdate()->findOrFail($stock->getKey());

            if ((float) $stock->quantity < $quantity) {
                throw StockException::insufficientStock($stock->name, $quantity, (float) $stock->quantity);
            }

            $remaining = $quantity;
            $movements = collect();

            $lots = $stock->lots()
                ->whereIn('status', [StockLotStatus::Available, StockLotStatus::Low])
                ->where('current_quantity', '>', 0)
                ->fifo()
                ->lockForUpdate()
                ->get();

            foreach ($lots as $lot) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min($remaining, (float) $lot->current_quantity);

                $movement = $stock->recordOut(
                    quantity: $take,
                    reason: $reason,
                    flockId: $flockId,
                    reference: $reference,
                    notes: trim(($notes ? $notes.' — ' : '')."Lot {$lot->lot_number}"),
                );

                $movement->update(['stock_lot_id' => $lot->id]);

                $lot->update([
                    'current_quantity' => max(0, (float) $lot->current_quantity - $take),
                ]);

                $movements->push($movement);
                $remaining -= $take;
            }

            // Stock non tracé par lot (entrées antérieures au suivi par lots) : sortie simple du reliquat
            if ($remaining > 0.0001) {
                $movements->push($stock->recordOut(
                    quantity: $remaining,
                    reason: $reason,
                    flockId: $flockId,
                    reference: $reference,
                    notes: $notes,
                ));
            }

            return $movements;
        });
    }

    /**
     * Sortie de stock : FIFO/FEFO par lots quand le produit en possède, sortie simple sinon.
     */
    public function recordOut(
        Stock $stock,
        float $quantity,
        StockMovementReason $reason = StockMovementReason::Consumption,
        ?int $flockId = null,
        ?string $reference = null,
        ?string $notes = null,
    ): Collection {
        if ($stock->hasAvailableLots()) {
            return $this->consumeFromLots($stock, $quantity, $flockId, $reference, $reason, $notes);
        }

        return collect([$stock->recordOut($quantity, $reason, $flockId, $reference, $notes)]);
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
