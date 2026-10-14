<?php

namespace App\Filament\Farm\Resources\StockMovements\Tables;

use App\Enums\StockMovementReason;
use App\Enums\StockMovementType;
use App\Models\StockMovement;
use App\Services\CurrentFiscalYear;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockMovementsTable
{
    public static function configure(Table $table): Table
    {
        return app(CurrentFiscalYear::class)->applyToTable($table, 'movement_date')
            ->modifyQueryUsing(fn ($query) => $query->with(['stock', 'stockLot', 'flock', 'user']))
            ->defaultSort('movement_date', 'desc')
            ->columns([
                TextColumn::make('movement_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('stock.name')
                    ->label('Produit')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge(),

                TextColumn::make('reason')
                    ->label('Motif')
                    ->badge(),

                TextColumn::make('quantity')
                    ->label('Qté')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(fn (StockMovement $record): string => ' '.$record->stock?->unit)
                    ->color(fn (StockMovement $record): string => $record->isIn() ? 'success' : 'danger')
                    ->weight('bold'),

                TextColumn::make('total_price')
                    ->label('Valeur')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' XOF')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('stockLot.lot_number')
                    ->label('Lot')
                    ->badge()
                    ->color('primary')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('flock.code')
                    ->label('Bande')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('reference')
                    ->label('Référence')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('user.name')
                    ->label('Utilisateur')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(StockMovementType::class)
                    ->multiple(),

                SelectFilter::make('reason')
                    ->options(StockMovementReason::class)
                    ->multiple(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
