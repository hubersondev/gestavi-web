<?php

namespace App\Filament\Farm\Resources\EquipmentMovements\Schemas;

use App\Enums\EquipmentMovementType;
use App\Models\Building;
use App\Models\Equipment;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EquipmentMovementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Mouvement d\'équipement')
                    ->columns(2)
                    ->schema([
                        Select::make('equipment_id')
                            ->label('Équipement')
                            ->options(function (): array {
                                $tenant = Filament::getTenant();

                                return Equipment::query()
                                    ->whereHas('building.farm', fn ($q) => $q->where('organisation_id', $tenant?->getKey()))
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (Equipment $e) => [
                                        $e->id => $e->inventory_number.' — '.$e->name,
                                    ])
                                    ->toArray();
                            })
                            ->required()
                            ->searchable()
                            ->preload(),

                        Select::make('movement_type')
                            ->label('Type de mouvement')
                            ->options(EquipmentMovementType::class)
                            ->required()
                            ->native(false),

                        Select::make('from_building_id')
                            ->label('Bâtiment d\'origine')
                            ->options(function (): array {
                                $tenant = Filament::getTenant();

                                return Building::query()
                                    ->whereHas('farm', fn ($q) => $q->where('organisation_id', $tenant?->getKey()))
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (Building $b) => [$b->id => $b->farm?->name.' → '.$b->name])
                                    ->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Select::make('to_building_id')
                            ->label('Bâtiment de destination')
                            ->options(function (): array {
                                $tenant = Filament::getTenant();

                                return Building::query()
                                    ->whereHas('farm', fn ($q) => $q->where('organisation_id', $tenant?->getKey()))
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (Building $b) => [$b->id => $b->farm?->name.' → '.$b->name])
                                    ->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        DateTimePicker::make('movement_date')
                            ->label('Date et heure')
                            ->default(now())
                            ->required()
                            ->native(false),

                        Textarea::make('reason')
                            ->label('Motif')
                            ->rows(2)
                            ->maxLength(500)
                            ->columnSpanFull(),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
