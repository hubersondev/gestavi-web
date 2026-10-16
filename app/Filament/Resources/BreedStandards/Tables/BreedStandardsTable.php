<?php

namespace App\Filament\Resources\BreedStandards\Tables;

use App\Models\BreedStandard;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BreedStandardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('age_days')
            ->defaultGroup('breed')
            ->columns([
                TextColumn::make('breed')->label('Souche')->searchable()->sortable(),
                TextColumn::make('bird_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === BreedStandard::TYPE_LAYER ? 'Pondeuse' : 'Chair')
                    ->color(fn (string $state): string => $state === BreedStandard::TYPE_LAYER ? 'warning' : 'success'),
                TextColumn::make('age_days')->label('Âge (j)')->numeric()->sortable(),
                TextColumn::make('expected_weight_g')->label('Poids (g)')->numeric(0)->placeholder('—'),
                TextColumn::make('expected_laying_rate')->label('Ponte (%)')->numeric(1)->placeholder('—'),
                TextColumn::make('expected_feed_per_day_g')->label('Aliment (g/j)')->numeric(0)->placeholder('—'),
                TextColumn::make('expected_mortality_cumul')->label('Mortalité cum. (%)')->numeric(1)->placeholder('—'),
                IconColumn::make('is_active')->label('Actif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('bird_type')
                    ->label('Type')
                    ->options([BreedStandard::TYPE_BROILER => 'Chair', BreedStandard::TYPE_LAYER => 'Pondeuse']),
                SelectFilter::make('breed')
                    ->label('Souche')
                    ->options(fn (): array => array_combine(BreedStandard::breeds(), BreedStandard::breeds())),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
