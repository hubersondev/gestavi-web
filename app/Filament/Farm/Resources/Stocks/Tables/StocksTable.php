<?php

namespace App\Filament\Farm\Resources\Stocks\Tables;

use App\Models\Stock;
use App\Models\StockCategory;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class StocksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('name')
                    ->label('Désignation')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Stock $record): ?string => $record->category?->name),

                TextColumn::make('quantity')
                    ->label('Quantité')
                    ->numeric(thousandsSeparator: ' ')
                    ->sortable()
                    ->suffix(fn (Stock $record): string => ' '.$record->unit)
                    ->color(fn (Stock $record): string => $record->isLowStock() ? 'danger' : 'gray')
                    ->weight('bold'),

                TextColumn::make('min_quantity')
                    ->label('Seuil')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(fn (Stock $record): string => ' '.$record->unit)
                    ->toggleable(),

                TextColumn::make('unit_price')
                    ->label('PU moyen')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' XOF')
                    ->toggleable(),

                TextColumn::make('total_value')
                    ->label('Valeur')
                    ->state(fn (Stock $record): float => $record->getTotalValue())
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' XOF')
                    ->color('primary')
                    ->toggleable(),

                TextColumn::make('farm.name')
                    ->label('Ferme')
                    ->placeholder('Organisation')
                    ->toggleable(),

                TextColumn::make('location')
                    ->label('Emplacement')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('stock_category_id')
                    ->label('Catégorie')
                    ->options(function (): array {
                        $tenant = Filament::getTenant();

                        return StockCategory::query()
                            ->where('organisation_id', $tenant?->getKey())
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->multiple(),

                Filter::make('low_stock')
                    ->label('Stock bas')
                    ->query(fn ($query) => $query->lowStock())
                    ->toggle(),

                TernaryFilter::make('is_active')
                    ->label('État')
                    ->default(true)
                    ->placeholder('Tous')
                    ->trueLabel('Actifs')
                    ->falseLabel('Inactifs'),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
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
