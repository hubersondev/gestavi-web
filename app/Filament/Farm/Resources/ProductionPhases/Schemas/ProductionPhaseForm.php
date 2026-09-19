<?php

namespace App\Filament\Farm\Resources\ProductionPhases\Schemas;

use App\Models\ChickenType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class ProductionPhaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Informations')
                    ->tabs([
                        // Onglet 1 : Informations générales
                        Tabs\Tab::make('Informations générales')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Section::make('Identification')
                                    ->schema([
                                        Select::make('chicken_type_id')
                                            ->label('Type de volaille')
                                            ->options(fn () => ChickenType::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->native(false),

                                        TextInput::make('name')
                                            ->label('Nom de la phase')
                                            ->required()
                                            ->maxLength(100)
                                            ->placeholder('Ex: Démarrage, Croissance, Ponte')
                                            ->columnSpan(2),

                                        TextInput::make('code')
                                            ->label('Code')
                                            ->maxLength(50)
                                            ->placeholder('Ex: DEM, CROIS, PONTE')
                                            ->helperText('Code unique de la phase'),
                                    ])
                                    ->columns(3),

                                Section::make('Période')
                                    ->schema([
                                        TextInput::make('start_day')
                                            ->label('Jour de début')
                                            ->required()
                                            ->numeric()
                                            ->minValue(0)
                                            ->suffix('jours')
                                            ->helperText('Âge de début de la phase'),

                                        TextInput::make('end_day')
                                            ->label('Jour de fin')
                                            ->required()
                                            ->numeric()
                                            ->minValue(0)
                                            ->suffix('jours')
                                            ->helperText('Âge de fin de la phase'),
                                    ])
                                    ->columns(2),

                                Section::make('Description')
                                    ->schema([
                                        Textarea::make('description')
                                            ->label('Description')
                                            ->rows(4)
                                            ->placeholder('Caractéristiques et objectifs de cette phase...')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        // Onglet 2 : Conditions environnementales
                        Tabs\Tab::make('Environnement')
                            ->icon('heroicon-o-sun')
                            ->schema([
                                Section::make('Températures')
                                    ->description('Températures recommandées pour cette phase')
                                    ->schema([
                                        TextInput::make('min_temperature')
                                            ->label('Température minimale')
                                            ->numeric()
                                            ->step(0.1)
                                            ->suffix('°C')
                                            ->placeholder('Ex: 18.0'),

                                        TextInput::make('max_temperature')
                                            ->label('Température maximale')
                                            ->numeric()
                                            ->step(0.1)
                                            ->suffix('°C')
                                            ->placeholder('Ex: 28.0'),
                                    ])
                                    ->columns(2),

                                Section::make('Humidité')
                                    ->description('Taux d\'humidité recommandé')
                                    ->schema([
                                        TextInput::make('humidity_min')
                                            ->label('Humidité minimale')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->step(0.1)
                                            ->suffix('%')
                                            ->placeholder('Ex: 50.0'),

                                        TextInput::make('humidity_max')
                                            ->label('Humidité maximale')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->step(0.1)
                                            ->suffix('%')
                                            ->placeholder('Ex: 70.0'),
                                    ])
                                    ->columns(2),

                                Section::make('Éclairage')
                                    ->schema([
                                        TextInput::make('light_hours')
                                            ->label('Heures d\'éclairage')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(24)
                                            ->step(0.5)
                                            ->suffix('heures/jour')
                                            ->placeholder('Ex: 16.0')
                                            ->helperText('Durée d\'éclairage quotidienne recommandée'),
                                    ]),
                            ]),

                        // Onglet 3 : Paramètres techniques
                        Tabs\Tab::make('Paramètres techniques')
                            ->icon('heroicon-o-cog')
                            ->schema([
                                Section::make('Densité')
                                    ->schema([
                                        TextInput::make('density_per_m2')
                                            ->label('Densité recommandée')
                                            ->numeric()
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->suffix('sujets/m²')
                                            ->placeholder('Ex: 8.00')
                                            ->helperText('Nombre de sujets par mètre carré'),
                                    ]),

                                Section::make('Alimentation')
                                    ->schema([
                                        TextInput::make('feed_type')
                                            ->label('Type d\'aliment recommandé')
                                            ->maxLength(255)
                                            ->placeholder('Ex: Démarrage pondeuse, Finition chair')
                                            ->helperText('Type ou formule d\'aliment approprié'),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
