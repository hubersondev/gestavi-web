<?php

namespace App\Filament\Farm\Resources\Slaughters\Tables;

use App\Filament\Farm\Resources\Slaughters\SlaughterResource;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SlaughtersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['flock', 'farm', 'destinationStock']))
            ->columns([
                TextColumn::make('slaughter_date')->label('Date')->date('d/m/Y')->sortable(),
                TextColumn::make('flock.code')->label('Bande')->searchable()->weight('bold'),
                TextColumn::make('farm.name')->label('Ferme')->toggleable(),
                TextColumn::make('birds_count')->label('Sujets')->numeric(thousandsSeparator: ' ')->sortable(),
                TextColumn::make('live_weight_kg')->label('Poids vif (kg)')->numeric(1, thousandsSeparator: ' ')->placeholder('—'),
                TextColumn::make('carcass_weight_kg')->label('Carcasses (kg)')->numeric(1, thousandsSeparator: ' ')->placeholder('—'),
                TextColumn::make('yield_percent')->label('Rendement')->suffix(' %')->placeholder('—'),
                TextColumn::make('destinationStock.name')->label('Stock destination')->placeholder('—')->toggleable(),
                TextColumn::make('recorder.name')->label('Saisi par')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('slaughter_date', 'desc')
            ->recordActions([
                EditAction::make()->visible(fn ($record) => SlaughterResource::canEdit($record)),
            ]);
    }
}
