<?php

namespace App\Filament\Farm\Resources\Farms\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Illuminate\Database\Eloquent\Builder;

class FarmForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Tabs::make('Informations')
                    ->tabs([
                        // Onglet Informations générales
                        Tabs\Tab::make('Informations générales')
                            ->icon('heroicon-o-building-office-2')
                            ->schema([
                                Section::make('Identité')
                                    ->schema([
                                        TextInput::make('code')
                                            ->label('Code')
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(255)
                                            ->placeholder('Ex: FARM-001'),
                                        TextInput::make('name')
                                            ->label('Nom de la ferme')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('Ex: Ferme Avicole de Bingerville'),
                                        Select::make('chicken_type_id')
                                            ->label("Type d'élevage")
                                            ->relationship('chickenType', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->placeholder('Sélectionner un type'),
                                        TextInput::make('capacity')
                                            ->label('Capacité totale')
                                            ->required()
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->suffix('sujets')
                                            ->helperText('Nombre maximum de volailles que la ferme peut accueillir'),

                                        Toggle::make('is_active')
                                            ->label('Ferme active')
                                            ->default(true)
                                            ->helperText('Désactiver temporairement cette ferme'),
                                    ])
                                    ->columns(2),
                            ]),

                        // Onglet Localisation
                        Tabs\Tab::make('Localisation')
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                Section::make('Adresse')
                                    ->schema([
                                        Textarea::make('address')
                                            ->label('Adresse complète')
                                            ->rows(3)
                                            ->placeholder('Ex: Rue des Palmiers, Quartier Résidentiel')
                                            ->columnSpanFull(),

                                        Select::make('city_id')
                                            ->label('Ville')
                                            ->relationship('city', 'name', function (Builder $query): void {
                                                $countryId = Filament::getTenant()?->country_id;
                                                if ($countryId) {
                                                    $query->where('country_id', $countryId);
                                                }
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->placeholder('Sélectionner une ville'),

                                    ])
                                    ->columns(2),

                                Section::make('Coordonnées GPS')
                                    ->description('Coordonnées GPS pour la localisation sur carte')
                                    ->schema([
                                        TextInput::make('latitude')
                                            ->label('Latitude')
                                            ->numeric()
                                            ->placeholder('Ex: 5.3600')
                                            ->step(0.000001),

                                        TextInput::make('longitude')
                                            ->label('Longitude')
                                            ->numeric()
                                            ->placeholder('Ex: -4.0083')
                                            ->step(0.000001),
                                    ])
                                    ->columns(2)
                                    ->collapsible(),
                            ]),

                        // Onglet Contact
                        Tabs\Tab::make('Contact')
                            ->icon('heroicon-o-phone')
                            ->schema([
                                Section::make('Coordonnées de contact')
                                    ->schema([
                                        TextInput::make('phone')
                                            ->label('Téléphone')
                                            ->tel()
                                            ->maxLength(20)
                                            ->placeholder('+225 07 XX XX XX XX'),

                                        TextInput::make('email')
                                            ->label('Email')
                                            ->email()
                                            ->maxLength(255)
                                            ->placeholder('contact@ferme.com'),
                                    ])
                                    ->columns(2),
                            ]),
                    ])
                    ->columnSpanFull()
            ]);
    }
}
