<?php

namespace App\Filament\Farm\Resources\MobileMoneyTransactions\Support;

use App\Enums\AccountType;
use App\Filament\Farm\Resources\MobileMoneyTransactions\MobileMoneyTransactionResource;
use App\Models\Account;
use App\Models\MobileMoneyTransaction;
use App\Models\Sale;
use App\Services\MobileMoney\MobileMoneyService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Throwable;

/**
 * Actions « Rapprocher » et « Ignorer » partagées par la table et la page de détail.
 */
class ReconciliationActions
{
    public static function match(): Action
    {
        return Action::make('match')
            ->label('Rapprocher')
            ->icon('heroicon-o-link')
            ->color('success')
            ->visible(fn (MobileMoneyTransaction $record): bool => $record->isReceived() && MobileMoneyTransactionResource::canPerformAction('match'))
            ->modalHeading('Rapprocher l\'encaissement avec une facture')
            ->modalDescription(fn (MobileMoneyTransaction $record): string => sprintf(
                '%s XOF reçus via %s (réf. %s). Seules les factures en attente dont le solde dû couvre ce montant sont proposées.',
                number_format((float) $record->amount, 0, ',', ' '),
                $record->operator->getLabel(),
                $record->external_id,
            ))
            ->schema([
                Select::make('sale_id')
                    ->label('Facture')
                    ->options(fn (MobileMoneyTransaction $record): array => app(MobileMoneyService::class)
                        ->candidateSales($record)
                        ->mapWithKeys(fn (Sale $sale): array => [$sale->id => sprintf(
                            '%s — %s — solde dû %s XOF',
                            $sale->invoice_number,
                            $sale->customer?->name ?? 'Comptant',
                            number_format((float) $sale->balance, 0, ',', ' '),
                        )])
                        ->toArray())
                    ->default(fn (MobileMoneyTransaction $record): ?int => app(MobileMoneyService::class)->autoMatchCandidate($record)?->id
                        ?? app(MobileMoneyService::class)->candidateSales($record, exactOnly: true)->first()?->id)
                    ->required()
                    ->searchable()
                    ->native(false),

                Select::make('account_id')
                    ->label('Compte mobile money crédité')
                    ->options(fn (MobileMoneyTransaction $record): array => Account::query()
                        ->where('organisation_id', $record->organisation_id)
                        ->where('type', AccountType::MobileMoney->value)
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray())
                    ->default(fn (MobileMoneyTransaction $record): ?int => $record->account_id
                        ?? app(MobileMoneyService::class)->merchantAccountFor((int) $record->organisation_id, $record->operator)?->id)
                    ->nullable()
                    ->native(false)
                    ->helperText('Le paiement crédite ce compte de trésorerie.'),
            ])
            ->action(function (MobileMoneyTransaction $record, array $data): void {
                try {
                    $sale = Sale::query()
                        ->where('organisation_id', $record->organisation_id)
                        ->findOrFail($data['sale_id']);

                    $payment = app(MobileMoneyService::class)->match(
                        $record,
                        $sale,
                        isset($data['account_id']) ? (int) $data['account_id'] : null,
                        Filament::auth()->user(),
                    );

                    Notification::make()
                        ->title('Encaissement rapproché')
                        ->body(sprintf('Paiement de %s XOF enregistré sur la facture %s.', number_format((float) $payment->amount, 0, ',', ' '), $sale->invoice_number))
                        ->success()
                        ->send();
                } catch (Throwable $e) {
                    Notification::make()
                        ->title('Rapprochement impossible')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }

    public static function ignore(): Action
    {
        return Action::make('ignore')
            ->label('Ignorer')
            ->icon('heroicon-o-eye-slash')
            ->color('gray')
            ->visible(fn (MobileMoneyTransaction $record): bool => $record->isReceived() && MobileMoneyTransactionResource::canPerformAction('ignore'))
            ->requiresConfirmation()
            ->modalHeading('Ignorer cet encaissement')
            ->modalDescription('L\'encaissement ne sera rapproché d\'aucune facture (dépôt sans facture, erreur opérateur…). Il reste consultable.')
            ->action(function (MobileMoneyTransaction $record): void {
                app(MobileMoneyService::class)->ignore($record, Filament::auth()->user());

                Notification::make()->title('Encaissement ignoré.')->success()->send();
            });
    }
}
