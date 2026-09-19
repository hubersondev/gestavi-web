<?php

namespace App\Filament\Farm\Resources\ProductionPhases\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use App\Filament\Farm\Resources\ProductionPhases\ProductionPhaseResource;
use Filament\Tables\Table;

class ProductionPhasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom de la phase')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn ($record) => $record->code),

                TextColumn::make('chickenType.name')
                    ->label('Type de volaille')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('period')
                    ->label('Période')
                    ->formatStateUsing(fn ($record) => "J{$record->start_day} - J{$record->end_day}")
                    ->description(fn ($record) => round(($record->end_day - $record->start_day + 1) / 7, 1).' semaines')
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('start_day', $direction)),

                TextColumn::make('temperature_range')
                    ->label('Températures')
                    ->formatStateUsing(fn ($record) => ($record->min_temperature ?? '?').' - '.($record->max_temperature ?? '?').' °C')
                    ->toggleable()
                    ->icon('heroicon-o-sun'),

                TextColumn::make('humidity_range')
                    ->label('Humidité')
                    ->formatStateUsing(fn ($record) => ($record->humidity_min ?? '?').' - '.($record->humidity_max ?? '?').' %')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('light_hours')
                    ->label('Éclairage')
                    ->numeric()
                    ->suffix(' h/j')
                    ->alignEnd()
                    ->toggleable()
                    ->placeholder('—'),

                TextColumn::make('density_per_m2')
                    ->label('Densité')
                    ->numeric()
                    ->suffix(' /m²')
                    ->alignEnd()
                    ->toggleable()
                    ->placeholder('—'),

                TextColumn::make('feed_type')
                    ->label('Type d\'aliment')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Non renseigné')
                    ->wrap(),

                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('chicken_type_id')
                    ->label('Type de volaille')
                    ->relationship('chickenType', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn ($record) => ProductionPhaseResource::canEdit($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => ProductionPhaseResource::canDeleteAny()),
                ]),
            ])
            ->defaultSort('start_day', 'asc');
    }
}
