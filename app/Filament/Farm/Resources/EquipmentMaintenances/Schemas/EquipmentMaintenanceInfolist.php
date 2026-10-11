<?php

namespace App\Filament\Farm\Resources\EquipmentMaintenances\Schemas;

use App\Enums\MaintenanceType;
use App\Models\EquipmentMaintenance;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EquipmentMaintenanceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Maintenance')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('equipment.name')
                            ->label('Équipement')
                            ->weight('bold')
                            ->helperText(fn (EquipmentMaintenance $record): ?string => $record->equipment?->inventory_number),

                        TextEntry::make('maintenance_type')
                            ->label('Type')
                            ->formatStateUsing(fn (EquipmentMaintenance $record): string => $record->getTypeLabel())
                            ->badge()
                            ->color(fn (EquipmentMaintenance $record): string => MaintenanceType::tryFrom($record->maintenance_type)?->getColor() ?? 'gray'),

                        TextEntry::make('status')
                            ->label('Statut')
                            ->formatStateUsing(fn (EquipmentMaintenance $record): string => $record->getStatusLabel())
                            ->badge()
                            ->color(fn (EquipmentMaintenance $record): string => $record->getStatusColor()),

                        TextEntry::make('title')
                            ->label('Titre'),
                    ]),

                Section::make('Planification')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('scheduled_date')->label('Date prévue')->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('performed_date')->label('Date réalisée')->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('next_maintenance_date')->label('Prochaine maintenance')->date('d/m/Y')->placeholder('—'),
                    ]),

                Section::make('Coûts')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('labor_cost')
                            ->label('Main d\'oeuvre')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF'),

                        TextEntry::make('parts_cost')
                            ->label('Pièces')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF'),

                        TextEntry::make('total_cost')
                            ->label('Total')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF')
                            ->weight('bold')
                            ->color('primary'),
                    ]),

                Section::make('État de l\'équipement')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('condition_before')
                            ->label('Avant')
                            ->formatStateUsing(fn ($state): string => \App\Enums\EquipmentCondition::tryFrom($state)?->getLabel() ?? $state ?? '—')
                            ->badge()
                            ->color(fn ($state): string => \App\Enums\EquipmentCondition::tryFrom($state)?->getColor() ?? 'gray'),

                        TextEntry::make('condition_after')
                            ->label('Après')
                            ->formatStateUsing(fn ($state): string => \App\Enums\EquipmentCondition::tryFrom($state)?->getLabel() ?? $state ?? '—')
                            ->badge()
                            ->color(fn ($state): string => \App\Enums\EquipmentCondition::tryFrom($state)?->getColor() ?? 'gray'),
                    ]),

                Section::make('Intervenant & notes')
                    ->schema([
                        TextEntry::make('external_technician')->label('Technicien')->placeholder('—'),
                        TextEntry::make('external_company')->label('Entreprise')->placeholder('—'),
                        TextEntry::make('performer.name')->label('Réalisé par (interne)')->placeholder('—'),
                        TextEntry::make('description')->label('Description des travaux')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('notes')->label('Notes')->placeholder('—')->columnSpanFull(),
                    ]),
            ]);
    }
}
