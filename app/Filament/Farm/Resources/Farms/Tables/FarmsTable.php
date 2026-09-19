<?php

namespace App\Filament\Farm\Resources\Farms\Tables;

use App\Filament\Farm\Resources\Farms\FarmResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FarmsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('chickenType.name')
                    ->label('Type')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('capacity')
                    ->label('Capacité')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('city.name')
                    ->label('Ville')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Téléphone')
                    ->placeholder('-'),
                TextColumn::make('email')
                    ->label('Email')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('buildings_count')
                    ->label('Bâtiments')
                    ->counts('buildings')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Création')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn ($record) => FarmResource::canEdit($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => FarmResource::canDeleteAny()),
                ]),
            ]);
    }
}
