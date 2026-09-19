<?php

namespace App\Filament\Farm\Resources\HealthPrograms\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class HealthProgramInfolist
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
                                        TextEntry::make('farm.name')
                                            ->label('Ferme')
                                            ->placeholder('Global (toutes les fermes)'),

                                        TextEntry::make('chickenType.name')
                                            ->label('Type de volaille')
                                            ->badge()
                                            ->color('info')
                                            ->placeholder('Tous types'),
                                        TextEntry::make('is_mandatory')
                                            ->label('Statut')
                                            ->formatStateUsing(fn(bool $state): string => $state ? 'Obligatoire' : 'Recommandé')
                                            ->badge()
                                            ->color(fn(bool $state): string => $state ? 'danger' : 'info'),
                                    ])
                                    ->columns(3),

                                Section::make('Traitement')
                                    ->schema([
                                        TextEntry::make('treatment_name')
                                            ->label('Nom du traitement')
                                            ->weight('bold'),

                                        TextEntry::make('treatment_type')
                                            ->label('Type')
                                            ->badge()
                                            ->formatStateUsing(fn($record) => $record->getTreatmentTypeLabel())
                                            ->color(fn($state) => match ($state) {
                                                'vaccin' => 'success',
                                                'antibiotique' => 'danger',
                                                'vitamine' => 'info',
                                                'vermifuge', 'anticoccidien', 'antiparasitaire' => 'warning',
                                                default => 'gray',
                                            }),

                                        TextEntry::make('scheduled_day')
                                            ->label('Jour prévu')
                                            ->formatStateUsing(fn($record) => 'J' . $record->scheduled_day . ' (semaine ' . $record->getScheduledWeek() . ')'),

                                    ])
                                    ->columns(3),
                            ]),

                        Tabs\Tab::make('Administration')
                            ->icon('heroicon-o-beaker')
                            ->schema([
                                Section::make('Mode d\'administration')
                                    ->schema([
                                        TextEntry::make('administration_route')
                                            ->label('Voie d\'administration')
                                            ->formatStateUsing(fn($record) => $record->getAdministrationRouteLabel())
                                            ->placeholder('-'),

                                        TextEntry::make('dosage')
                                            ->label('Dosage')
                                            ->placeholder('Non spécifié'),
                                    ])
                                    ->columns(2),

                                Section::make('Délai d\'attente')
                                    ->schema([
                                        TextEntry::make('withdrawal_days')
                                            ->label('Délai d\'attente')
                                            ->formatStateUsing(fn($record) => $record->withdrawal_days > 0
                                                ? $record->withdrawal_days . ' jours'
                                                : 'Aucun')
                                            ->badge()
                                            ->color(fn($record) => $record->withdrawal_days > 0 ? 'warning' : 'success'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Notes')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        TextEntry::make('notes')
                                            ->label('Notes et recommandations')
                                            ->placeholder('Aucune note')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
