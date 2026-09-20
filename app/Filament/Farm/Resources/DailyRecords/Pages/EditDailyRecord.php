<?php

namespace App\Filament\Farm\Resources\DailyRecords\Pages;

use App\Exceptions\StockException;
use App\Filament\Farm\Resources\DailyRecords\DailyRecordResource;
use App\Models\DailyRecord;
use App\Services\FlockService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditDailyRecord extends EditRecord
{
    protected static string $resource = DailyRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * Correction d'un relevé : si l'aliment avait été déstocké, la différence
     * est régularisée par un mouvement d'ajustement ; refusé si le stock est insuffisant.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var DailyRecord $record */
        $previousKg = (float) $record->feed_consumed_kg;
        $newKg = (float) ($data['feed_consumed_kg'] ?? $previousKg);
        $service = app(FlockService::class);

        try {
            return DB::transaction(function () use ($record, $data, $previousKg, $newKg, $service): DailyRecord {
                $record->update($data);
                $record->refresh();

                if ($record->feed_movement_id) {
                    $service->adjustFeedConsumption($record, $previousKg, $newKg);
                } elseif ($record->feed_stock_id && $newKg > 0) {
                    $service->consumeFeedFromStock($record, $newKg);
                }

                return $record;
            });
        } catch (StockException $e) {
            Notification::make()
                ->title('Stock d\'aliment insuffisant')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }
    }
}
