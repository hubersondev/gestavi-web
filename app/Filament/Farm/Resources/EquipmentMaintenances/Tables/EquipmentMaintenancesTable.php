<?php

namespace App\Filament\Farm\Resources\EquipmentMaintenances\Tables;

use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Models\EquipmentMaintenance;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EquipmentMaintenancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['equipment', 'performer']))
            ->defaultSort('scheduled_date', 'desc')
            ->columns([
                TextColumn::make('equipment.name')
                    ->label('Équipement')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (EquipmentMaintenance $record): ?string => $record->equipment?->inventory_number),

                TextColumn::make('title')
                    ->label('Titre')
                    ->searchable()
                    ->limit(40),

                TextColumn::make('maintenance_type')
                    ->label('Type')
                    ->formatStateUsing(fn (EquipmentMaintenance $record): string => $record->getTypeLabel())
                    ->badge()
                    ->color(fn (EquipmentMaintenance $record): string => MaintenanceType::tryFrom($record->maintenance_type)?->getColor() ?? 'gray'),

                TextColumn::make('scheduled_date')
                    ->label('Prévue')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn (EquipmentMaintenance $record): string => $record->isOverdue() ? 'danger' : 'gray'),

                TextColumn::make('performed_date')
                    ->label('Réalisée')
                    ->date('d/m/Y')
                    ->placeholder('—'),

                TextColumn::make('total_cost')
                    ->label('Coût')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' XOF')
                    ->placeholder('—')
                    ->summarize(Sum::make()->label('Total')->numeric(thousandsSeparator: ' ')),

                TextColumn::make('status')
                    ->label('Statut')
                    ->formatStateUsing(fn (EquipmentMaintenance $record): string => $record->getStatusLabel())
                    ->badge()
                    ->color(fn (EquipmentMaintenance $record): string => $record->getStatusColor()),

                TextColumn::make('performer.name')
                    ->label('Réalisé par')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('maintenance_type')
                    ->label('Type')
                    ->options(MaintenanceType::class)
                    ->multiple(),

                SelectFilter::make('status')
                    ->options(MaintenanceStatus::class)
                    ->multiple(),

                Filter::make('overdue')
                    ->label('En retard')
                    ->query(fn ($query) => $query->overdue())
                    ->toggle(),

                Filter::make('upcoming')
                    ->label('Prochains 7 jours')
                    ->query(fn ($query) => $query->upcoming(7))
                    ->toggle(),
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
