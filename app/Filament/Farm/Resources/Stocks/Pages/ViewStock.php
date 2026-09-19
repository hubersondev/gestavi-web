<?php

namespace App\Filament\Farm\Resources\Stocks\Pages;

use App\Enums\StockMovementReason;
use App\Filament\Farm\Resources\Stocks\StockResource;
use App\Models\Flock;
use App\Models\Stock;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewStock extends ViewRecord
{
    protected static string $resource = StockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recordIn')
                ->label('Enregistrer une entrée')
                ->icon('heroicon-o-arrow-down-circle')
                ->color('success')
                ->visible(fn (): bool => StockResource::canPerformAction('record_movement'))
                ->schema([
                    TextInput::make('quantity')
                        ->label('Quantité entrée')
                        ->numeric()
                        ->required()
                        ->minValue(0.01),

                    Select::make('reason')
                        ->label('Motif')
                        ->options([
                            StockMovementReason::Purchase->value => StockMovementReason::Purchase->getLabel(),
                            StockMovementReason::TransferIn->value => StockMovementReason::TransferIn->getLabel(),
                            StockMovementReason::Return->value => StockMovementReason::Return->getLabel(),
                            StockMovementReason::Adjustment->value => StockMovementReason::Adjustment->getLabel(),
                        ])
                        ->default(StockMovementReason::Purchase->value)
                        ->required()
                        ->native(false),

                    TextInput::make('unit_price')
                        ->label('Prix unitaire (optionnel)')
                        ->numeric()
                        ->suffix('XOF')
                        ->minValue(0),

                    TextInput::make('reference')
                        ->label('Référence')
                        ->maxLength(255),

                    Textarea::make('notes')->label('Notes')->rows(2),
                ])
                ->action(function (Stock $record, array $data): void {
                    try {
                        $record->recordIn(
                            quantity: (float) $data['quantity'],
                            reason: StockMovementReason::from($data['reason']),
                            unitPrice: ! empty($data['unit_price']) ? (float) $data['unit_price'] : null,
                            reference: $data['reference'] ?? null,
                            notes: $data['notes'] ?? null,
                        );
                        Notification::make()->title('Entrée enregistrée')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Erreur')->body($e->getMessage())->danger()->send();
                    }
                }),

            Action::make('recordOut')
                ->label('Enregistrer une sortie')
                ->icon('heroicon-o-arrow-up-circle')
                ->color('danger')
                ->visible(fn (Stock $record): bool => $record->hasStock() && StockResource::canPerformAction('record_movement'))
                ->schema([
                    TextInput::make('quantity')
                        ->label('Quantité sortie')
                        ->numeric()
                        ->required()
                        ->minValue(0.01)
                        ->maxValue(fn (Stock $record): float => (float) $record->quantity),

                    Select::make('reason')
                        ->label('Motif')
                        ->options([
                            StockMovementReason::Consumption->value => StockMovementReason::Consumption->getLabel(),
                            StockMovementReason::Sale->value => StockMovementReason::Sale->getLabel(),
                            StockMovementReason::Loss->value => StockMovementReason::Loss->getLabel(),
                            StockMovementReason::TransferOut->value => StockMovementReason::TransferOut->getLabel(),
                        ])
                        ->default(StockMovementReason::Consumption->value)
                        ->required()
                        ->native(false),

                    Select::make('flock_id')
                        ->label('Bande bénéficiaire')
                        ->options(function (): array {
                            $tenant = Filament::getTenant();

                            return Flock::query()
                                ->whereHas('building.farm', fn ($q) => $q->where('organisation_id', $tenant?->getKey()))
                                ->orderByDesc('start_date')
                                ->get()
                                ->mapWithKeys(fn (Flock $flock) => [$flock->id => $flock->code.' – '.$flock->name])
                                ->toArray();
                        })
                        ->searchable()
                        ->nullable()
                        ->helperText('Pour l\'analytique par bande'),

                    TextInput::make('reference')->label('Référence')->maxLength(255),
                    Textarea::make('notes')->label('Notes')->rows(2),
                ])
                ->action(function (Stock $record, array $data): void {
                    try {
                        $record->recordOut(
                            quantity: (float) $data['quantity'],
                            reason: StockMovementReason::from($data['reason']),
                            flockId: ! empty($data['flock_id']) ? (int) $data['flock_id'] : null,
                            reference: $data['reference'] ?? null,
                            notes: $data['notes'] ?? null,
                        );
                        Notification::make()->title('Sortie enregistrée')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Erreur')->body($e->getMessage())->danger()->send();
                    }
                }),

            EditAction::make(),
        ];
    }
}
