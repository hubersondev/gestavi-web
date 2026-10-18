<?php

namespace App\Filament\Farm\Resources\Transactions\Pages;

use App\Exceptions\TransactionException;
use App\Filament\Farm\Resources\Transactions\TransactionResource;
use App\Models\Transaction;
use App\Services\TransactionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reconcile')
                ->label('Rapprocher')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (Transaction $record): bool => ! $record->isReconciled() && TransactionResource::canPerformAction('reconcile'))
                ->requiresConfirmation()
                ->action(function (Transaction $record): void {
                    $record->reconcile();
                    Notification::make()->title('Transaction rapprochée.')->success()->send();
                }),

            Action::make('unreconcile')
                ->label('Annuler le rapprochement')
                ->icon('heroicon-o-x-circle')
                ->color('warning')
                ->visible(fn (Transaction $record): bool => $record->isReconciled() && ! $record->belongsToBankReconciliation() && TransactionResource::canPerformAction('reconcile'))
                ->requiresConfirmation()
                ->action(function (Transaction $record): void {
                    try {
                        $record->unreconcile();
                    } catch (TransactionException $e) {
                        Notification::make()->title('Annulation impossible')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Rapprochement annulé.')->success()->send();
                }),

            Action::make('reverse')
                ->label('Contrepasser')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(fn (Transaction $record): bool => ! $record->isReconciled() && TransactionResource::canPerformAction('reverse'))
                ->requiresConfirmation()
                ->modalDescription('Crée une écriture opposée pour annuler cette transaction. Le montant sera reversé sur le solde du compte.')
                ->schema([
                    Textarea::make('reason')
                        ->label('Motif de la contrepassation')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (Transaction $record, array $data): void {
                    try {
                        $reversal = app(TransactionService::class)->reverseTransaction($record, $data['reason']);

                        Notification::make()
                            ->title('Transaction contrepassée.')
                            ->body("Écriture inverse créée : #{$reversal->id}")
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Impossible de contrepasser')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

        ];
    }
}
