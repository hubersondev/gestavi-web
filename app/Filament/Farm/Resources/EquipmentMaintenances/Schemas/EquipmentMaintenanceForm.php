<?php

namespace App\Filament\Farm\Resources\EquipmentMaintenances\Schemas;

use App\Enums\EquipmentCondition;
use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Models\Equipment;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class EquipmentMaintenanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Maintenance')
                    ->tabs([
                        Tabs\Tab::make('Informations générales')
                            ->icon('heroicon-o-wrench-screwdriver')
                            ->schema([
                                Section::make('Équipement & type')
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

                                        Select::make('maintenance_type')
                                            ->label('Type')
                                            ->options(MaintenanceType::class)
                                            ->required()
                                            ->native(false),

                                        TextInput::make('title')
                                            ->label('Titre')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('Ex : Vidange moteur groupe électrogène')
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('Planification')
                                    ->columns(3)
                                    ->schema([
                                        DatePicker::make('scheduled_date')
                                            ->label('Date prévue')
                                            ->native(false)
                                            ->displayFormat('d/m/Y'),

                                        DatePicker::make('performed_date')
                                            ->label('Date réalisée')
                                            ->native(false)
                                            ->displayFormat('d/m/Y'),

                                        DatePicker::make('next_maintenance_date')
                                            ->label('Prochaine maintenance')
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->helperText('Met à jour automatiquement l\'équipement'),

                                        Select::make('status')
                                            ->label('Statut')
                                            ->options(MaintenanceStatus::class)
                                            ->default(MaintenanceStatus::Scheduled)
                                            ->required()
                                            ->native(false)
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Coûts')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Section::make('Coûts de maintenance')
                                    ->columns(3)
                                    ->schema([
                                        TextInput::make('labor_cost')
                                            ->label('Main d\'oeuvre')
                                            ->numeric()
                                            ->default(0)
                                            ->suffix('XOF'),

                                        TextInput::make('parts_cost')
                                            ->label('Pièces')
                                            ->numeric()
                                            ->default(0)
                                            ->suffix('XOF'),

                                        TextInput::make('total_cost')
                                            ->label('Coût total')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->suffix('XOF')
                                            ->helperText('Calculé automatiquement'),
                                    ]),

                                Section::make('Intervenant externe')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('external_technician')
                                            ->label('Technicien')
                                            ->maxLength(255),

                                        TextInput::make('external_company')
                                            ->label('Entreprise')
                                            ->maxLength(255),
                                    ]),
                            ]),

                        Tabs\Tab::make('État & notes')
                            ->icon('heroicon-o-clipboard-document-check')
                            ->schema([
                                Section::make('Condition de l\'équipement')
                                    ->columns(2)
                                    ->schema([
                                        Select::make('condition_before')
                                            ->label('État avant maintenance')
                                            ->options(EquipmentCondition::class)
                                            ->native(false),

                                        Select::make('condition_after')
                                            ->label('État après maintenance')
                                            ->options(EquipmentCondition::class)
                                            ->native(false),
                                    ]),

                                Section::make()
                                    ->schema([
                                        Textarea::make('description')
                                            ->label('Description des travaux')
                                            ->rows(3)
                                            ->columnSpanFull(),

                                        Textarea::make('notes')
                                            ->label('Notes')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
