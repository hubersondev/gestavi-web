<?php

namespace App\Filament\Farm\Resources\ProductionPhases\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class ProductionPhaseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Informations')
                    ->tabs([
                        Tabs\Tab::make('Vue d\'ensemble')
                            ->icon('heroicon-o-eye')
                            ->schema([
                                Section::make('Informations générales')
                                    ->schema([
                                        TextEntry::make('chickenType.name')
                                            ->label('Type de volaille')
                                            ->badge()
                                            ->color('info'),

                                        TextEntry::make('name')
                                            ->label('Nom de la phase')
                                            ->weight('bold'),

                                        TextEntry::make('code')
                                            ->label('Code')
                                            ->placeholder('Non renseigné'),
                                    ])
                                    ->columns(3),

                                Section::make('Période')
                                    ->schema([
                                        TextEntry::make('start_day')
                                            ->label('Jour de début')
                                            ->numeric()
                                            ->suffix(' jours'),

                                        TextEntry::make('end_day')
                                            ->label('Jour de fin')
                                            ->numeric()
                                            ->suffix(' jours'),
                                    ])
                                    ->columns(2),

                                Section::make('Description')
                                    ->schema([
                                        TextEntry::make('description')
                                            ->label('Description')
                                            ->placeholder('Aucune description')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Environnement')
                            ->icon('heroicon-o-sun')
                            ->schema([
                                Section::make('Températures')
                                    ->schema([
                                        TextEntry::make('min_temperature')
                                            ->label('Température minimale')
                                            ->numeric()
                                            ->suffix(' °C')
                                            ->placeholder('Non renseignée'),

                                        TextEntry::make('max_temperature')
                                            ->label('Température maximale')
                                            ->numeric()
                                            ->suffix(' °C')
                                            ->placeholder('Non renseignée'),
                                    ])
                                    ->columns(2),

                                Section::make('Humidité')
                                    ->schema([
                                        TextEntry::make('humidity_min')
                                            ->label('Humidité minimale')
                                            ->numeric()
                                            ->suffix(' %')
                                            ->placeholder('Non renseignée'),

                                        TextEntry::make('humidity_max')
                                            ->label('Humidité maximale')
                                            ->numeric()
                                            ->suffix(' %')
                                            ->placeholder('Non renseignée'),
                                    ])
                                    ->columns(2),

                                Section::make('Éclairage')
                                    ->schema([
                                        TextEntry::make('light_hours')
                                            ->label('Heures d\'éclairage')
                                            ->numeric()
                                            ->suffix(' h/jour')
                                            ->placeholder('Non renseigné'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Paramètres techniques')
                            ->icon('heroicon-o-cog')
                            ->schema([
                                Section::make('Densité')
                                    ->schema([
                                        TextEntry::make('density_per_m2')
                                            ->label('Densité recommandée')
                                            ->numeric()
                                            ->suffix(' sujets/m²')
                                            ->placeholder('Non renseignée'),
                                    ]),

                                Section::make('Alimentation')
                                    ->schema([
                                        TextEntry::make('feed_type')
                                            ->label('Type d\'aliment')
                                            ->placeholder('Non renseigné'),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
