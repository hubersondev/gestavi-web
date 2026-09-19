<?php

namespace App\Filament\Farm\Resources\EquipmentMaintenances\Pages;

use App\Enums\EquipmentCondition;
use App\Filament\Farm\Resources\EquipmentMaintenances\EquipmentMaintenanceResource;
use App\Models\EquipmentMaintenance;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewEquipmentMaintenance extends ViewRecord
{
    protected static string $resource = EquipmentMaintenanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('start')
                ->label('Démarrer')
                ->icon('heroicon-o-play')
                ->color('info')
                ->visible(fn (EquipmentMaintenance $record): bool => $record->isScheduled() && EquipmentMaintenanceResource::canPerformAction('update'))
                ->requiresConfirmation()
                ->modalDescription('Passe la maintenance en cours et met l\'équipement en statut "en maintenance".')
                ->action(function (EquipmentMaintenance $record): void {
                    try {
                        $record->start();
                        Notification::make()->title('Maintenance démarrée.')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Erreur')->body($e->getMessage())->danger()->send();
                    }
                }),

            Action::make('complete')
                ->label('Terminer')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (EquipmentMaintenance $record): bool => $record->isInProgress() && EquipmentMaintenanceResource::canPerformAction('update'))
                ->schema([
                    Select::make('condition_after')
                        ->label('État après maintenance')
                        ->options(EquipmentCondition::class)
                        ->native(false),

                    DatePicker::make('next_maintenance_date')
                        ->label('Prochaine maintenance (optionnel)')
                        ->native(false),
                ])
                ->action(function (EquipmentMaintenance $record, array $data): void {
                    try {
                        $record->complete(
                            conditionAfter: $data['condition_after'] ?? null,
                            nextMaintenanceDate: ! empty($data['next_maintenance_date']) ? \Carbon\Carbon::parse($data['next_maintenance_date']) : null,
                        );
                        Notification::make()->title('Maintenance terminée.')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Erreur')->body($e->getMessage())->danger()->send();
                    }
                }),

            Action::make('cancel')
                ->label('Annuler')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (EquipmentMaintenance $record): bool => ($record->isScheduled() || $record->isInProgress()) && EquipmentMaintenanceResource::canPerformAction('delete'))
                ->requiresConfirmation()
                ->action(function (EquipmentMaintenance $record): void {
                    $record->cancel();
                    Notification::make()->title('Maintenance annulée.')->warning()->send();
                }),

            EditAction::make()
                ->visible(fn (EquipmentMaintenance $record): bool => $record->isScheduled()),
        ];
    }
}
