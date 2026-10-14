<?php

namespace App\Filament\Farm\Resources\Transactions\Tables;

use App\Enums\TransactionCategory;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Services\CurrentFiscalYear;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return app(CurrentFiscalYear::class)->applyToTable($table, 'transaction_date')
            ->defaultSort('transaction_date', 'desc')
            ->columns([
                TextColumn::make('transaction_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->description(fn ($record): string => '#'.str_pad((string) $record->id, 8, '0', STR_PAD_LEFT)),

                TextColumn::make('account.name')
                    ->label('Compte')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge(),

                TextColumn::make('amount')
                    ->label('Montant')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' XOF')
                    ->sortable()
                    ->weight('bold')
                    ->color(fn ($record): string => $record->type === TransactionType::Credit ? 'success' : 'danger')
                    ->summarize(Sum::make()->label('Total')->numeric(thousandsSeparator: ' ')),

                TextColumn::make('category')
                    ->label('Catégorie')
                    ->badge(),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->description),

                TextColumn::make('balance_after')
                    ->label('Solde après')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' XOF')
                    ->toggleable(),

                IconColumn::make('is_reconciled')
                    ->label('Rapprochée')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('user.name')
                    ->label('Utilisateur')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('account_id')
                    ->label('Compte')
                    ->options(function (): array {
                        $tenant = Filament::getTenant();

                        return Account::query()
                            ->where('organisation_id', $tenant?->getKey())
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->searchable(),

                SelectFilter::make('type')
                    ->options(TransactionType::class),

                SelectFilter::make('category')
                    ->options(TransactionCategory::class)
                    ->multiple(),

                TernaryFilter::make('is_reconciled')
                    ->label('Rapprochée'),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
