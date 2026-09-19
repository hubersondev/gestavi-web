<?php

namespace App\Filament\Farm\Resources\AccountTransfers\Tables;

use App\Enums\AccountTransferStatus;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AccountTransfersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('transfer_date', 'desc')
            ->columns([
                TextColumn::make('reference')
                    ->label('Référence')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('transfer_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('fromAccount.name')
                    ->label('Source')
                    ->icon('heroicon-o-arrow-up-circle')
                    ->iconColor('danger')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('toAccount.name')
                    ->label('Destination')
                    ->icon('heroicon-o-arrow-down-circle')
                    ->iconColor('success')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Montant')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' XOF')
                    ->sortable()
                    ->weight('bold')
                    ->color('primary')
                    ->summarize(Sum::make()->label('Total')->numeric(thousandsSeparator: ' ')),

                TextColumn::make('fees')
                    ->label('Frais')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' XOF')
                    ->color('warning')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),

                TextColumn::make('user.name')
                    ->label('Effectué par')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(AccountTransferStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
