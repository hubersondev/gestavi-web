<?php

namespace App\Filament\Farm\Resources\EquipmentMovements\Schemas;

use App\Enums\EquipmentMovementType;
use App\Models\Building;
use App\Models\Equipment;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

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
                            // Garde-fou serveur : la valeur soumise doit appartenir à l'organisation courante
                            ->rules(fn (): array => [Rule::exists('equipments', 'id')->where('organisation_id', Filament::getTenant()?->getKey())])
                            ->label('Équipement')
                            ->options(function (): array {
                                $tenant = Filament::getTenant();

                                return Equipment::query()
                                    ->whereHas('farm', fn ($q) => $q->where('organisation_id', $tenant?->getKey()))
                                    ->tap(fn ($q) => app(CurrentFarm::class)->restrictForCurrentUser($q, 'farm_id'))
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
