<?php

namespace App\Filament\Farm\Resources\Equipments\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'movements';

    protected static ?string $title = 'Historique des déplacements';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('movement_date')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('movement_type')
                    ->label('Type')
                    ->formatStateUsing(fn ($record) => $record->getTypeLabel())
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'entry' => 'success',
                        'exit' => 'danger',
                        'transfer' => 'info',
                        'loan' => 'warning',
                        'return' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('fromBuilding.name')
                    ->label('De')
                    ->placeholder('Stock')
                    ->sortable(),

                TextColumn::make('toBuilding.name')
                    ->label('Vers')
                    ->placeholder('Stock')
                    ->sortable(),

                TextColumn::make('mover.name')
                    ->label('Par')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('reason')
                    ->label('Motif')
                    ->placeholder('-')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('movement_date', 'desc');
    }
}
