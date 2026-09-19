<?php

namespace App\Filament\Farm\Resources\StockLots\Pages;

use App\Enums\StockMovementReason;
use App\Filament\Farm\Resources\StockLots\StockLotResource;
use App\Models\Stock;
use App\Models\StockLot;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateStockLot extends CreateRecord
{
    protected static string $resource = StockLotResource::class;

    protected function associateRecordWithTenant(Model $record, Model $tenant): void
    {
        // Le lot appartient au tenant via stock → organisation ; aucune FK directe.
    }

    /**
     * La réception d'un lot est une entrée de stock : le lot est créé puis
     * Stock::recordIn enregistre le mouvement et incrémente la quantité du produit.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $stock = Stock::query()
            ->where('organisation_id', Filament::getTenant()?->getKey())
            ->findOrFail($data['stock_id']);

        return DB::transaction(function () use ($stock, $data): StockLot {
            $data['current_quantity'] = $data['initial_quantity'];

            /** @var StockLot $lot */
            $lot = $stock->lots()->create($data);

            $stock->recordIn(
                quantity: (float) $lot->initial_quantity,
                reason: StockMovementReason::Purchase,
                unitPrice: (float) $lot->unit_price,
                stockLotId: $lot->id,
                reference: $lot->lot_number,
                notes: 'Réception du lot '.$lot->lot_number,
            );

            return $lot;
        });
    }
}
