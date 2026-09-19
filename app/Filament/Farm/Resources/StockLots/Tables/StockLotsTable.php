<?php

namespace App\Filament\Farm\Resources\StockLots\Tables;

use App\Enums\StockLotStatus;
use App\Models\StockLot;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class StockLotsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['stock', 'supplier']))
            ->defaultSort('reception_date', 'desc')
            ->columns([
                TextColumn::make('lot_number')
                    ->label('N° Lot')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('stock.name')
                    ->label('Produit')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('supplier.name')
                    ->label('Fournisseur')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('reception_date')
                    ->label('Reçu le')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('expiry_date')
                    ->label('Péremption')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->color(fn (StockLot $record): string => $record->isExpired() ? 'danger' : ($record->isExpiringSoon() ? 'warning' : 'gray'))
                    ->sortable(),

                TextColumn::make('current_quantity')
                    ->label('Qté restante')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(fn (StockLot $record): string => ' '.$record->stock?->unit)
                    ->weight('bold'),

                TextColumn::make('initial_quantity')
                    ->label('Qté initiale')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(fn (StockLot $record): string => ' '.$record->stock?->unit)
                    ->toggleable(),

                TextColumn::make('unit_price')
                    ->label('PU')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' XOF')
                    ->toggleable(),

                TextColumn::make('total_value')
                    ->label('Valeur')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' XOF')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(StockLotStatus::class)
                    ->multiple(),

                Filter::make('expiring_soon')
                    ->label('Périme bientôt (30j)')
                    ->query(fn ($query) => $query->expiringSoon(30))
                    ->toggle(),

                Filter::make('expired')
                    ->label('Périmés')
                    ->query(fn ($query) => $query->expired())
                    ->toggle(),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
