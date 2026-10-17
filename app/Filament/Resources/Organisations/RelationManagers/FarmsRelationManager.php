<?php

namespace App\Filament\Resources\Organisations\RelationManagers;

use App\Enums\FlockStatus;
use App\Models\Farm;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fermes d'une organisation, avec leur activité : bâtiments, bandes actives, effectif.
 */
class FarmsRelationManager extends RelationManager
{
    protected static string $relationship = 'farms';

    protected static ?string $title = 'Fermes';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('city')
                ->withCount(['buildings', 'flocks as active_flocks_count' => fn (Builder $q) => $q->where('status', FlockStatus::Active->value)])
                ->withSum(['flocks as birds_sum' => fn (Builder $q) => $q->where('status', FlockStatus::Active->value)], 'quantity_current'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('code')->label('Code')->color('gray'),
                TextColumn::make('name')->label('Ferme')->weight('medium')->searchable(),
                TextColumn::make('city.name')->label('Ville')->placeholder('—'),
                TextColumn::make('buildings_count')->label('Bâtiments')->alignRight(),
                TextColumn::make('active_flocks_count')->label('Bandes actives')->alignRight(),
                TextColumn::make('birds_sum')->label('Effectif')->numeric(locale: 'fr')->alignRight()->placeholder('0'),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('latitude')
                    ->label('Météo')
                    ->state(fn (Farm $record): string => ($record->latitude !== null && $record->longitude !== null) || $record->city_id ? 'Localisée' : 'Sans position')
                    ->badge()
                    ->color(fn (Farm $record): string => ($record->latitude !== null && $record->longitude !== null) || $record->city_id ? 'success' : 'warning'),
            ])
            ->emptyStateHeading('Aucune ferme');
    }
}
