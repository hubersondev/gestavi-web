<?php

namespace App\Filament\Farm\Resources\DailyRecords\Schemas;

use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class DailyRecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Informations')
                    ->tabs([
                        Tabs\Tab::make("Vue d'ensemble")
                            ->icon('heroicon-o-eye')
                            ->schema([
                                Section::make('Contexte')
                                    ->columns(3)
                                    ->schema([
                                        TextEntry::make('flock.building.farm.name')
                                            ->label('Ferme')
                                            ->weight('bold')
                                            ->hidden(function () {
                                                $membership = Filament::auth()->user()?->membershipIn(Filament::getTenant());

                                                return $membership && app(CurrentFarm::class)->get($membership) !== null;
                                            }),

                                        TextEntry::make('flock.building.name')
                                            ->label('Bâtiment'),

                                        TextEntry::make('flock.code')
                                            ->label('Bande')
                                            ->weight('bold'),

                                        TextEntry::make('record_date')
                                            ->label('Date du suivi')
                                            ->date('d/m/Y')
                                            ->weight('bold'),

                                        TextEntry::make('age_days')
                                            ->label('Âge')
                                            ->suffix(' jours'),

                                        TextEntry::make('recorder.name')
                                            ->label('Enregistré par')
                                            ->placeholder('—'),
                                    ]),

                                Section::make('Mortalité')
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('mortality')
                                            ->label('Mortalité')
                                            ->suffix(' sujets')
                                            ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                                            ->weight(fn ($state) => $state > 0 ? 'bold' : null),

                                        TextEntry::make('mortality_cause')
                                            ->label('Cause')
                                            ->placeholder('Aucune'),
                                    ]),

                                Section::make("Production d'œufs")
                                    ->columns(3)
                                    ->hidden(fn ($record) => $record->flock?->chickenType?->name !== 'Pondeuse')
                                    ->schema([
                                        TextEntry::make('eggs_collected')
                                            ->label('Œufs collectés')
                                            ->suffix(' œufs')
                                            ->color(fn ($state) => $state > 0 ? 'success' : null),

                                        TextEntry::make('eggs_broken')
                                            ->label('Œufs cassés')
                                            ->suffix(' œufs')
                                            ->color(fn ($state) => $state > 0 ? 'warning' : null),

                                        TextEntry::make('eggs_sold')
                                            ->label('Œufs vendus')
                                            ->suffix(' œufs'),
                                    ]),

                                Section::make('Consommation')
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('feed_consumed_kg')
                                            ->label('Aliment consommé')
                                            ->suffix(' kg'),

                                        TextEntry::make('water_consumed_l')
                                            ->label('Eau consommée')
                                            ->suffix(' L'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Pesée & Santé')
                            ->icon('heroicon-o-heart')
                            ->schema([
                                Section::make('Pesée')
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('average_weight_g')
                                            ->label('Poids moyen')
                                            ->suffix(' g')
                                            ->placeholder('Non renseigné'),

                                        TextEntry::make('birds_weighed')
                                            ->label('Sujets pesés')
                                            ->suffix(' sujets')
                                            ->placeholder('—'),
                                    ]),

                                Section::make('Santé')
                                    ->schema([
                                        TextEntry::make('health_observations')
                                            ->label('Observations de santé')
                                            ->placeholder('Aucune observation')
                                            ->columnSpanFull(),

                                        TextEntry::make('treatments')
                                            ->label('Traitements appliqués')
                                            ->placeholder('Aucun traitement')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Environnement & Notes')
                            ->icon('heroicon-o-sun')
                            ->schema([
                                Section::make('Conditions climatiques')
                                    ->columns(3)
                                    ->schema([
                                        TextEntry::make('temperature_min')
                                            ->label('Température min')
                                            ->suffix(' °C')
                                            ->placeholder('—'),

                                        TextEntry::make('temperature_max')
                                            ->label('Température max')
                                            ->suffix(' °C')
                                            ->placeholder('—'),

                                        TextEntry::make('humidity_percent')
                                            ->label('Humidité')
                                            ->suffix(' %')
                                            ->placeholder('—'),
                                    ]),

                                Section::make('Notes')
                                    ->schema([
                                        TextEntry::make('notes')
                                            ->label('Observations générales')
                                            ->placeholder('Aucune note')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
