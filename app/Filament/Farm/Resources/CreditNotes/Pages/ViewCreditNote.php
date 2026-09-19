<?php

namespace App\Filament\Farm\Resources\CreditNotes\Pages;

use App\Enums\CreditNoteStatus;
use App\Filament\Farm\Resources\CreditNotes\CreditNoteResource;
use App\Models\Account;
use App\Models\CreditNote;
use App\Services\CurrentFarm;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
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
                ->modalDescription(function (CreditNote $record): string {
                    $refund = $record->getRefundableAmount();
                    $credit = (float) $record->amount - $refund;

                    $text = sprintf(
                        'Facture %s : %s XOF viendront en réduction du solde dû (sans mouvement de trésorerie).',
                        $record->sale?->invoice_number ?? '—',
                        number_format($credit, 0, ',', ' '),
                    );

                    if ($refund > 0) {
                        $text .= sprintf(' La facture étant déjà réglée à hauteur de ce montant, %s XOF seront remboursés au client par débit du compte choisi.', number_format($refund, 0, ',', ' '));
                    }

                    return $text;
                })
                ->schema(fn (CreditNote $record): array => $record->getRefundableAmount() > 0 ? [
                    Select::make('refund_account_id')
                        ->label('Compte à débiter pour le remboursement')
                        ->options(function (): array {
                            $tenant = Filament::getTenant();

                            return Account::query()
                                ->where('organisation_id', $tenant?->getKey())
                                ->tap(fn ($q) => app(CurrentFarm::class)->restrictForCurrentUser($q, 'farm_id', allowOrganisationLevel: true))
                                ->active()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->default(fn () => $record->sale?->account_id)
                        ->required()
                        ->searchable()
                        ->native(false),
                ] : [])
                ->action(function (CreditNote $record, array $data): void {
                    try {
                        $record->apply(isset($data['refund_account_id']) ? (int) $data['refund_account_id'] : null);
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
