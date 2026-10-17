<?php

namespace App\Filament\Farm\Resources\BankReconciliations\Tables;

use App\Enums\BankReconciliationStatus;
use App\Filament\Farm\Resources\Accounts\AccountResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BankReconciliationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('statement_date', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['account', 'creator'])->withCount(['lines', 'transactions']))
            ->columns([
                TextColumn::make('account.name')
                    ->label('Compte')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('statement_date')
                    ->label('Date du relevé')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('statement_balance')
                    ->label('Solde du relevé')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' XOF')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->sortable(),

                TextColumn::make('transactions_count')
                    ->label('Transactions pointées')
                    ->badge()
                    ->color('success'),

                TextColumn::make('lines_count')
                    ->label('Lignes du relevé')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('completed_at')
                    ->label('Clôturé le')
                    ->dateTime('d/m/Y')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('creator.name')
                    ->label('Ouvert par')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('account_id')
                    ->label('Compte')
                    ->options(fn (): array => AccountResource::getEloquentQuery()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),

                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(BankReconciliationStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
