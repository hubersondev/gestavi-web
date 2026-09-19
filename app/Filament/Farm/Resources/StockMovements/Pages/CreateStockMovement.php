<?php

namespace App\Filament\Farm\Resources\StockMovements\Pages;

use App\Enums\StockMovementReason;
use App\Enums\StockMovementType;
use App\Exceptions\StockException;
use App\Filament\Farm\Resources\StockMovements\StockMovementResource;
use App\Models\Stock;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStockMovement extends CreateRecord
{
    protected static string $resource = StockMovementResource::class;

    protected function associateRecordWithTenant(Model $record, Model $tenant): void
    {
        // Le mouvement appartient au tenant via stock → organisation ; aucune FK directe.
    }

    /**
     * Toute création passe par Stock::recordIn / recordOut afin que la quantité
     * du produit et le mouvement restent cohérents (plus de mouvement « fantôme »).
     */
    protected function handleRecordCreation(array $data): Model
    {
        $stock = Stock::query()
            ->where('organisation_id', Filament::getTenant()?->getKey())
            ->findOrFail($data['stock_id']);

        $type = $data['type'] instanceof StockMovementType ? $data['type'] : StockMovementType::from($data['type']);
        $reason = $data['reason'] instanceof StockMovementReason ? $data['reason'] : StockMovementReason::from($data['reason']);
        $quantity = (float) $data['quantity'];

        try {
            $movement = match ($type) {
                StockMovementType::In => $stock->recordIn(
                    quantity: $quantity,
                    reason: $reason,
                    unitPrice: isset($data['unit_price']) ? (float) $data['unit_price'] : null,
                    flockId: $data['flock_id'] ?? null,
                    reference: $data['reference'] ?? null,
                    notes: $data['notes'] ?? null,
                ),
                StockMovementType::Out => $stock->recordOut(
                    quantity: $quantity,
                    reason: $reason,
                    flockId: $data['flock_id'] ?? null,
                    reference: $data['reference'] ?? null,
                    notes: $data['notes'] ?? null,
                ),
                default => throw new StockException(
                    'Les ajustements passent par un inventaire et les transferts inter-fermes ne sont pas encore disponibles.'
                ),
            };
        } catch (StockException $e) {
            Notification::make()
                ->title('Mouvement refusé')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }

        if (! empty($data['movement_date'])) {
            $movement->update(['movement_date' => $data['movement_date']]);
        }

        return $movement;
    }
}
