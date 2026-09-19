<?php

namespace App\Filament\Farm\Resources\EquipmentMovements\Tables;

use App\Enums\EquipmentMovementType;
use App\Models\EquipmentMovement;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EquipmentMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['equipment', 'fromBuilding.farm', 'toBuilding.farm', 'mover']))
            ->defaultSort('movement_date', 'desc')
            ->columns([
                TextColumn::make('movement_date')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('equipment.name')
                    ->label('Équipement')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (EquipmentMovement $record): ?string => $record->equipment?->inventory_number),

                TextColumn::make('movement_type')
                    ->label('Type')
                    ->formatStateUsing(fn (EquipmentMovement $record): string => $record->getTypeLabel())
                    ->badge()
                    ->color(fn (EquipmentMovement $record): string => EquipmentMovementType::tryFrom($record->movement_type)?->getColor() ?? 'gray'),

                TextColumn::make('fromBuilding.name')
                    ->label('Depuis')
                    ->description(fn (EquipmentMovement $record): ?string => $record->fromBuilding?->farm?->name)
                    ->placeholder('Stock')
                    ->icon('heroicon-o-arrow-up-circle')
                    ->iconColor('danger'),

                TextColumn::make('toBuilding.name')
                    ->label('Vers')
                    ->description(fn (EquipmentMovement $record): ?string => $record->toBuilding?->farm?->name)
                    ->placeholder('Stock')
                    ->icon('heroicon-o-arrow-down-circle')
                    ->iconColor('success'),

                TextColumn::make('reason')
                    ->label('Motif')
                    ->limit(30)
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('mover.name')
                    ->label('Effectué par')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('movement_type')
                    ->label('Type')
                    ->options(EquipmentMovementType::class)
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
