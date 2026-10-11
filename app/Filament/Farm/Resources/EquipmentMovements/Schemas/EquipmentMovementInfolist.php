<?php

namespace App\Filament\Farm\Resources\EquipmentMovements\Schemas;

use App\Enums\EquipmentMovementType;
use App\Models\EquipmentMovement;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EquipmentMovementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Mouvement')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('movement_date')
                            ->label('Date et heure')
                            ->dateTime('d/m/Y H:i'),

                        TextEntry::make('movement_type')
                            ->label('Type')
                            ->formatStateUsing(fn (EquipmentMovement $record): string => $record->getTypeLabel())
                            ->badge()
                            ->color(fn (EquipmentMovement $record): string => EquipmentMovementType::tryFrom($record->movement_type)?->getColor() ?? 'gray'),

                        TextEntry::make('mover.name')
                            ->label('Effectué par')
                            ->placeholder('—'),
                    ]),

                Section::make('Équipement')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('equipment.name')
                            ->label('Équipement')
                            ->weight('bold'),

                        TextEntry::make('equipment.inventory_number')
                            ->label('N° inventaire')
                            ->badge()
                            ->color('gray'),
                    ]),

                Section::make('Trajet')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('fromBuilding.name')
                            ->label('Bâtiment d\'origine')
                            ->icon('heroicon-o-arrow-up-circle')
                            ->iconColor('danger')
                            ->placeholder('Stock / Externe')
                            ->helperText(fn (EquipmentMovement $record): ?string => $record->fromBuilding?->farm?->name),

                        TextEntry::make('toBuilding.name')
                            ->label('Bâtiment de destination')
                            ->icon('heroicon-o-arrow-down-circle')
                            ->iconColor('success')
                            ->placeholder('Stock / Externe')
                            ->helperText(fn (EquipmentMovement $record): ?string => $record->toBuilding?->farm?->name),

                        TextEntry::make('description_computed')
                            ->label('Description')
                            ->state(fn (EquipmentMovement $record): string => $record->getDescription())
                            ->columnSpanFull(),
                    ]),

                Section::make('Motif & notes')
                    ->schema([
                        TextEntry::make('reason')
                            ->label('Motif')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
