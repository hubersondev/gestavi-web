<?php

namespace App\Filament\Farm\Resources\AccountTransfers\Pages;

use App\Filament\Farm\Resources\AccountTransfers\AccountTransferResource;
use App\Models\AccountTransfer;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewAccountTransfer extends ViewRecord
{
    protected static string $resource = AccountTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('complete')
                ->label('Compléter le transfert')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (AccountTransfer $record): bool => $record->isPending() && AccountTransferResource::canPerformAction('complete'))
                ->requiresConfirmation()
                ->modalDescription(fn (AccountTransfer $record): string => sprintf(
                    'Débiter %s XOF (dont %s XOF de frais) du compte "%s" et créditer %s XOF sur "%s" ?',
                    number_format($record->getTotalAmount(), 0, ',', ' '),
                    number_format((float) $record->fees, 0, ',', ' '),
                    $record->fromAccount?->name ?? '—',
                    number_format((float) $record->amount, 0, ',', ' '),
                    $record->toAccount?->name ?? '—',
                ))
                ->action(function (AccountTransfer $record): void {
                    try {
                        $record->complete();
                        Notification::make()->title('Transfert complété.')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Transfert impossible')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('cancel')
                ->label('Annuler')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (AccountTransfer $record): bool => $record->canBeCancelled() && AccountTransferResource::canPerformAction('cancel'))
                ->requiresConfirmation()
                ->action(function (AccountTransfer $record): void {
                    $record->cancel();
                    Notification::make()->title('Transfert annulé.')->warning()->send();
                }),

            Action::make('reverse')
                ->label('Contrepasser')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn (AccountTransfer $record): bool => $record->isCompleted() && AccountTransferResource::canPerformAction('reverse'))
                ->requiresConfirmation()
                ->modalDescription('Crée deux transactions opposées pour annuler l\'effet de ce transfert sur les deux comptes.')
                ->action(function (AccountTransfer $record): void {
                    try {
                        $record->reverse();
                        Notification::make()->title('Transfert contrepassé.')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Contrepassation impossible')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            EditAction::make()
                ->visible(fn (AccountTransfer $record): bool => $record->isPending()),

            DeleteAction::make()
                ->visible(fn (AccountTransfer $record): bool => $record->isPending()),
        ];
    }
}
