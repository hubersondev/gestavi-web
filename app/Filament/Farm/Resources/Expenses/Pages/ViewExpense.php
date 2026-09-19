<?php

namespace App\Filament\Farm\Resources\Expenses\Pages;

use App\Filament\Farm\Resources\Expenses\ExpenseResource;
use App\Models\Account;
use App\Models\Expense;
use App\Services\ExpenseService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ViewExpense extends ViewRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadPdf')
                ->label('Télécharger PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (Expense $record): StreamedResponse {
                    $expense = $record->load(['supplier', 'farm', 'flock', 'account', 'organisation', 'recorder']);

                    $pdf = Pdf::loadView('pdf.expense', [
                        'expense' => $expense,
                    ])->setPaper('a4', 'portrait');

                    $filename = 'depense-'.$expense->expense_number.'.pdf';

                    return response()->streamDownload(
                        fn () => print ($pdf->output()),
                        $filename,
                        ['Content-Type' => 'application/pdf'],
                    );
                }),

            Action::make('recordPayment')
                ->label('Enregistrer un paiement')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn (Expense $record): bool => $record->hasBalance() && ! $record->isCancelled() && ExpenseResource::canPerformAction('mark_paid'))
                ->schema([
                    TextInput::make('amount')
                        ->label('Montant à payer')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->suffix('XOF')
                        ->default(fn (Expense $record): float => (float) $record->balance),

                    Select::make('account_id')
                        ->label('Compte à débiter')
                        ->options(function (): array {
                            $tenant = Filament::getTenant();

                            return Account::query()
                                ->where('organisation_id', $tenant?->getKey())
                                ->active()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->searchable()
                        ->required()
                        ->default(fn (Expense $record): ?int => $record->account_id)
                        ->helperText('Le solde du compte sera diminué du montant payé'),
                ])
                ->action(function (Expense $record, array $data): void {
                    try {
                        app(ExpenseService::class)->recordPayment(
                            expense: $record,
                            amount: (float) $data['amount'],
                            accountId: (int) $data['account_id'],
                        );

                        Notification::make()->title('Paiement enregistré')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Paiement impossible')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('cancel')
                ->label('Annuler la dépense')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (Expense $record): bool => ! $record->isCancelled() && ExpenseResource::canPerformAction('cancel'))
                ->requiresConfirmation()
                ->modalDescription('Annule la dépense et contrepasse les paiements comptables éventuels.')
                ->action(function (Expense $record): void {
                    try {
                        app(ExpenseService::class)->cancelExpense($record);
                        Notification::make()->title('Dépense annulée.')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Annulation impossible')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            EditAction::make()
                ->visible(fn (Expense $record): bool => ! $record->isCancelled() && ! $record->isPaid()),

            DeleteAction::make()
                ->visible(fn (Expense $record): bool => $record->isCancelled() || (float) $record->paid_amount === 0.0),
        ];
    }
}
