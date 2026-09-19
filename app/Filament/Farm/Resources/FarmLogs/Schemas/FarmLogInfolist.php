<?php

namespace App\Filament\Farm\Resources\FarmLogs\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FarmLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations générales')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('log_date')
                            ->label('Date')
                            ->date('d/m/Y')
                            ->weight('bold'),

                        TextEntry::make('log_time')
                            ->label('Heure')
                            ->time('H:i')
                            ->placeholder('-'),

                        TextEntry::make('category')
                            ->label('Catégorie')
                            ->badge(),

                        TextEntry::make('priority')
                            ->label('Priorité')
                            ->badge(),

                        TextEntry::make('farm.name')
                            ->label('Ferme'),

                        TextEntry::make('flock.code')
                            ->label('Bande')
                            ->formatStateUsing(fn ($state, $record) => $state ? "{$state}" . ($record->flock?->name ? " — {$record->flock->name}" : '') : null)
                            ->placeholder('Ferme entière'),

                        TextEntry::make('building.name')
                            ->label('Bâtiment')
                            ->placeholder('-'),

                        TextEntry::make('title')
                            ->label('Titre')
                            ->weight('bold')
                            ->columnSpanFull(),
                    ]),

                Section::make('Description')
                    ->schema([
                        TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('Aucune description.')
                            ->columnSpanFull(),

                        TextEntry::make('actions_taken')
                            ->label('Actions entreprises')
                            ->placeholder('Aucune action renseignée.')
                            ->columnSpanFull(),
                    ])
                    ->hidden(fn ($record) => empty($record?->description) && empty($record?->actions_taken)),

                Section::make('Conditions')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('weather')
                            ->label('Météo')
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'sunny' => 'Ensoleillé',
                                'cloudy' => 'Nuageux',
                                'rainy' => 'Pluvieux',
                                'windy' => 'Venteux',
                                'stormy' => 'Orageux',
                                'hot' => 'Très chaud',
                                'cold' => 'Froid',
                                'humid' => 'Très humide',
                                default => $state,
                            })
                            ->placeholder('-'),

                        TextEntry::make('temperature')
                            ->label('Température')
                            ->suffix(' °C')
                            ->placeholder('-'),

                        TextEntry::make('humidity')
                            ->label('Humidité')
                            ->suffix(' %')
                            ->placeholder('-'),
                    ])
                    ->hidden(fn ($record) => ! $record?->weather && ! $record?->temperature && ! $record?->humidity),

                Section::make('Suivi')
                    ->columns(2)
                    ->schema([
                        IconEntry::make('requires_follow_up')
                            ->label('Suivi requis')
                            ->boolean(),

                        TextEntry::make('follow_up_date')
                            ->label('Date de suivi')
                            ->date('d/m/Y')
                            ->placeholder('-')
                            ->color(fn ($record) => $record->isOverdueFollowUp() ? 'danger' : null),

                        IconEntry::make('is_resolved')
                            ->label('Résolu')
                            ->boolean(),

                        TextEntry::make('resolved_at')
                            ->label('Résolu le')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('-')
                            ->hidden(fn ($record) => ! $record?->is_resolved),

                        TextEntry::make('resolver.name')
                            ->label('Résolu par')
                            ->placeholder('-')
                            ->hidden(fn ($record) => ! $record?->is_resolved),
                    ]),

                Section::make('Notes')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('')
                            ->placeholder('Aucune note.')
                            ->columnSpanFull(),
                    ])
                    ->hidden(fn ($record) => empty($record?->notes)),

                Section::make('Métadonnées')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('recorder.name')
                            ->label('Enregistré par'),

                        TextEntry::make('created_at')
                            ->label('Date de création')
                            ->dateTime(),

                        TextEntry::make('updated_at')
                            ->label('Dernière modification')
                            ->dateTime(),
                    ])
                    ->collapsed(),
            ]);
    }
}
