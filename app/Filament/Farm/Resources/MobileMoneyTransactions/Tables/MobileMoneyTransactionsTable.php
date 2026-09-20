<?php

namespace App\Filament\Farm\Resources\MobileMoneyTransactions\Tables;

use App\Enums\MobileMoneyOperator;
use App\Filament\Farm\Resources\MobileMoneyTransactions\Support\ReconciliationActions;
use App\Services\MobileMoney\TransactionStatus;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MobileMoneyTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Reçu le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('operator')
                    ->label('Opérateur')
                    ->badge(),

                TextColumn::make('amount')
                    ->label('Montant')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' XOF')
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('payer_name')
                    ->label('Payeur')
                    ->placeholder('—')
                    ->description(fn ($record): ?string => $record->payer_phone)
                    ->searchable(['payer_name', 'payer_phone']),

                TextColumn::make('reference')
                    ->label('Message / référence')
                    ->placeholder('—')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('external_id')
                    ->label('ID opérateur')
                    ->copyable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),

                TextColumn::make('payment.sale.invoice_number')
                    ->label('Facture')
                    ->badge()
                    ->color('primary')
                    ->placeholder('—'),

                TextColumn::make('account.name')
                    ->label('Compte')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Statut')->options(TransactionStatus::class)->default(TransactionStatus::Received->value),
                SelectFilter::make('operator')->label('Opérateur')->options(MobileMoneyOperator::class)->multiple(),
            ])
            ->recordActions([
                ViewAction::make(),
                ReconciliationActions::match(),
                ReconciliationActions::ignore(),
            ]);
    }
}
