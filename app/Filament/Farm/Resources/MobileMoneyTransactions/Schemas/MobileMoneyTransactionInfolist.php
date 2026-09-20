<?php

namespace App\Filament\Farm\Resources\MobileMoneyTransactions\Schemas;

use App\Models\MobileMoneyTransaction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MobileMoneyTransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Encaissement')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('operator')->label('Opérateur')->badge(),
                        TextEntry::make('amount')->label('Montant')->numeric(thousandsSeparator: ' ')->suffix(' XOF')->weight('bold')->size('lg'),
                        TextEntry::make('occurred_at')->label('Reçu le')->dateTime('d/m/Y H:i'),
                        TextEntry::make('status')->label('Statut')->badge(),
                        TextEntry::make('external_id')->label('Identifiant opérateur')->copyable(),
                        TextEntry::make('direction')->label('Sens')->badge(),
                        TextEntry::make('currency')->label('Devise'),
                        TextEntry::make('account.name')->label('Compte marchand')->placeholder('Non identifié'),
                    ]),

                Section::make('Payeur')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('payer_name')->label('Nom')->placeholder('—'),
                        TextEntry::make('payer_phone')->label('Téléphone')->placeholder('—')->copyable(),
                        TextEntry::make('reference')->label('Message / référence')->placeholder('—'),
                    ]),

                Section::make('Rapprochement')
                    ->columns(3)
                    ->hidden(fn (MobileMoneyTransaction $record): bool => $record->isReceived())
                    ->schema([
                        TextEntry::make('payment.sale.invoice_number')->label('Facture')->badge()->color('primary')->placeholder('—'),
                        TextEntry::make('payment.amount')->label('Paiement enregistré')->numeric(thousandsSeparator: ' ')->suffix(' XOF')->placeholder('—'),
                        TextEntry::make('matcher.name')->label('Par')->placeholder('Automatique'),
                        TextEntry::make('matched_at')->label('Le')->dateTime('d/m/Y H:i')->placeholder('—'),
                    ]),

                Section::make('Notification brute')
                    ->collapsed()
                    ->schema([
                        TextEntry::make('raw_payload')
                            ->label('')
                            ->state(fn (MobileMoneyTransaction $record): string => json_encode($record->raw_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—')
                            ->fontFamily('mono')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
