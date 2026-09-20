<?php

namespace App\Filament\Farm\Resources\Products\Tables;

use App\Enums\ProductKind;
use App\Filament\Farm\Resources\Products\ProductResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Désignation')->searchable()->sortable()->weight('bold'),
                TextColumn::make('code')->label('Code')->searchable()->placeholder('—')->toggleable(),
                TextColumn::make('kind')->label('Type')->badge(),
                TextColumn::make('unit')->label('Unité'),
                TextColumn::make('default_unit_price')->label('Prix par défaut')->numeric(0, thousandsSeparator: ' ')->suffix(' XOF')->sortable(),
                TextColumn::make('default_tax_percent')->label('TVA')->suffix(' %')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_live_birds')->label('Sujets vivants')->boolean(),
                TextColumn::make('stock.name')->label('Stock associé')->placeholder('—')->toggleable(),
                IconColumn::make('is_active')->label('Actif')->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('kind')->label('Type')->options(ProductKind::class),
                TernaryFilter::make('is_active')->label('Actif')->default(true),
            ])
            ->recordActions([
                EditAction::make()->visible(fn ($record) => ProductResource::canEdit($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => ProductResource::canDeleteAny()),
                ]),
            ]);
    }
}
