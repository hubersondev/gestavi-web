<?php

namespace App\Filament\Farm\Resources\StockInventories\Tables;

use App\Enums\StockInventoryStatus;
use App\Models\StockInventory;
use App\Services\CurrentFiscalYear;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockInventoriesTable
{
    public static function configure(Table $table): Table
    {
        return app(CurrentFiscalYear::class)->applyToTable($table, 'inventory_date')
            ->modifyQueryUsing(fn ($query) => $query->with(['stock', 'conductor', 'validator']))
            ->defaultSort('inventory_date', 'desc')
            ->columns([
                TextColumn::make('inventory_ref')
                    ->label('Référence')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->weight('bold'),

                TextColumn::make('inventory_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('stock.name')
                    ->label('Produit')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('theoretical_quantity')
                    ->label('Théorique')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(fn (StockInventory $record): string => ' '.$record->stock?->unit),

                TextColumn::make('physical_quantity')
                    ->label('Physique')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(fn (StockInventory $record): string => ' '.$record->stock?->unit),

                TextColumn::make('difference')
                    ->label('Écart')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(fn (StockInventory $record): string => ' '.$record->stock?->unit)
                    ->color(fn (StockInventory $record): string => $record->hasDifference() ? ($record->isPositiveDifference() ? 'success' : 'danger') : 'gray')
                    ->weight('bold'),

                TextColumn::make('difference_value')
                    ->label('Valeur écart')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' XOF')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),

                TextColumn::make('conductor.name')
                    ->label('Compté par')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('validator.name')
                    ->label('Validé par')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(StockInventoryStatus::class)
                    ->multiple(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
