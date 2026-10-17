<?php

namespace App\Filament\Farm\Resources\Accounts\Tables;

use App\Enums\AccountType;
use App\Filament\Farm\Resources\Accounts\Actions\AccountFundingActions;
use App\Models\Account;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Account $record) => $record->code),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge(),

                TextColumn::make('farm.name')
                    ->label('Ferme')
                    ->searchable()
                    ->placeholder('Organisation')
                    ->toggleable(),

                TextColumn::make('bank_name')
                    ->label('Banque / Opérateur')
                    ->formatStateUsing(function (Account $record): string {
                        if ($record->isBank()) {
                            return $record->bank_name ?? '—';
                        }
                        if ($record->isMobileMoney()) {
                            return $record->operator?->getLabel() ?? '—';
                        }

                        return '—';
                    })
                    ->toggleable(),

                TextColumn::make('current_balance')
                    ->label('Solde actuel')
                    ->numeric(thousandsSeparator: ' ')
                    ->sortable()
                    ->suffix(' XOF')
                    ->weight('bold')
                    ->color(fn ($state): string => (float) $state > 0 ? 'success' : ((float) $state < 0 ? 'danger' : 'gray'))
                    ->summarize(Sum::make()->label('Total')->numeric(thousandsSeparator: ' ')),

                TextColumn::make('transactions_count')
                    ->label('Transactions')
                    ->counts('transactions')
                    ->badge()
                    ->color('primary')
                    ->toggleable(),

                IconColumn::make('is_default')
                    ->label('Par défaut')
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('type')
                    ->options(AccountType::class)
                    ->multiple(),

                TernaryFilter::make('is_active')
                    ->label('État')
                    ->placeholder('Tous')
                    ->trueLabel('Actifs uniquement')
                    ->falseLabel('Inactifs uniquement')
                    ->default(true),

                TernaryFilter::make('is_default')
                    ->label('Compte par défaut'),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                AccountFundingActions::replenish(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
