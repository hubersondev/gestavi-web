<?php

namespace App\Filament\Farm\Resources\CreditNotes\Pages;

use App\Enums\CreditNoteStatus;
use App\Filament\Farm\Resources\CreditNotes\CreditNoteResource;
use App\Models\CreditNote;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewCreditNote extends ViewRecord
{
    protected static string $resource = CreditNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markAsIssued')
                ->label('Marquer comme émis')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn (CreditNote $record): bool => $record->isDraft() && CreditNoteResource::canPerformAction('update'))
                ->requiresConfirmation()
                ->action(function (CreditNote $record): void {
                    $record->update(['status' => CreditNoteStatus::Issued]);
                    Notification::make()->title('Avoir marqué comme émis.')->success()->send();
                }),

            Action::make('apply')
                ->label('Imputer sur la facture')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (CreditNote $record): bool => $record->canBeApplied() && CreditNoteResource::canPerformAction('apply'))
                ->requiresConfirmation()
                ->modalDescription(fn (CreditNote $record): string => sprintf(
                    'Diminuer le solde de la facture %s de %s XOF (débit du compte d\'encaissement si présent) ?',
                    $record->sale?->invoice_number ?? '—',
                    number_format((float) $record->amount, 0, ',', ' '),
                ))
                ->action(function (CreditNote $record): void {
                    try {
                        $record->apply();
                        Notification::make()->title('Avoir imputé avec succès.')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Imputation impossible')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('cancel')
                ->label('Annuler')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (CreditNote $record): bool => ! $record->isApplied() && ! $record->isCancelled() && CreditNoteResource::canPerformAction('update'))
                ->requiresConfirmation()
                ->action(function (CreditNote $record): void {
                    try {
                        $record->cancel();
                        Notification::make()->title('Avoir annulé.')->warning()->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Annulation impossible')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            EditAction::make()
                ->visible(fn (CreditNote $record): bool => $record->isDraft()),
        ];
    }
}
