<?php

namespace App\Filament\Farm\Resources\HealthIncidents\Schemas;

use App\Enums\IncidentCategory;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class HealthIncidentInfolist
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

                                        TextEntry::make('flock.code')
                                            ->label('Bande')
                                            ->weight('bold'),

                                        TextEntry::make('reporter.name')
                                            ->label('Signalé par')
                                            ->placeholder('—'),
                                    ]),

                                Section::make("Détails de l'incident")
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('title')
                                            ->label('Titre')
                                            ->weight('bold')
                                            ->columnSpanFull(),

                                        TextEntry::make('incident_date')
                                            ->label("Date de l'incident")
                                            ->date('d/m/Y'),

                                        TextEntry::make('detected_date')
                                            ->label('Date de détection')
                                            ->date('d/m/Y')
                                            ->placeholder('—'),

                                        TextEntry::make('severity')
                                            ->label('Gravité')
                                            ->badge()
                                            ->color(fn (IncidentSeverity $state) => $state->color())
                                            ->icon(fn (IncidentSeverity $state) => $state->icon())
                                            ->formatStateUsing(fn (IncidentSeverity $state) => $state->label()),

                                        TextEntry::make('category')
                                            ->label('Catégorie')
                                            ->badge()
                                            ->color(fn (IncidentCategory $state) => $state->color())
                                            ->formatStateUsing(fn (IncidentCategory $state) => $state->label()),

                                        TextEntry::make('status')
                                            ->label('Statut')
                                            ->badge()
                                            ->color(fn (IncidentStatus $state) => $state->color())
                                            ->icon(fn (IncidentStatus $state) => $state->icon())
                                            ->formatStateUsing(fn (IncidentStatus $state) => $state->label()),
                                    ]),
                            ]),

                        Tabs\Tab::make('Symptômes & Diagnostic')
                            ->icon('heroicon-o-magnifying-glass')
                            ->schema([
                                Section::make('Observations')
                                    ->schema([
                                        TextEntry::make('symptoms')
                                            ->label('Symptômes observés')
                                            ->columnSpanFull(),

                                        TextEntry::make('suspected_disease')
                                            ->label('Maladie suspectée')
                                            ->placeholder('—'),

                                        TextEntry::make('confirmed_disease')
                                            ->label('Maladie confirmée')
                                            ->placeholder('—'),
                                    ])
                                    ->columns(2),

                                Section::make('Impact')
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('birds_affected')
                                            ->label('Sujets affectés')
                                            ->suffix(' sujets')
                                            ->color('warning')
                                            ->weight('bold'),

                                        TextEntry::make('birds_dead')
                                            ->label('Sujets morts')
                                            ->suffix(' sujets')
                                            ->color(fn ($state) => $state > 0 ? 'danger' : null)
                                            ->weight(fn ($state) => $state > 0 ? 'bold' : null),
                                    ]),
                            ]),

                        Tabs\Tab::make('Traitement')
                            ->icon('heroicon-o-beaker')
                            ->schema([
                                Section::make('Traitement appliqué')
                                    ->schema([
                                        TextEntry::make('treatment_applied')
                                            ->label('Description')
                                            ->placeholder('Aucun traitement renseigné')
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('Dates & Coût')
                                    ->columns(3)
                                    ->schema([
                                        TextEntry::make('treatment_start_date')
                                            ->label('Début')
                                            ->date('d/m/Y')
                                            ->placeholder('—'),

                                        TextEntry::make('treatment_end_date')
                                            ->label('Fin')
                                            ->date('d/m/Y')
                                            ->placeholder('—'),

                                        TextEntry::make('treatment_cost')
                                            ->label('Coût')
                                            ->money('XOF')
                                            ->placeholder('—'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Quarantaine & Résolution')
                            ->icon('heroicon-o-shield-exclamation')
                            ->schema([
                                Section::make('Quarantaine')
                                    ->columns(3)
                                    ->schema([
                                        TextEntry::make('quarantine_applied')
                                            ->label('Quarantaine')
                                            ->badge()
                                            ->color(fn ($state) => $state ? 'warning' : 'gray')
                                            ->formatStateUsing(fn ($state) => $state ? 'Oui' : 'Non'),

                                        TextEntry::make('quarantine_start_date')
                                            ->label('Début quarantaine')
                                            ->date('d/m/Y')
                                            ->placeholder('—'),

                                        TextEntry::make('quarantine_end_date')
                                            ->label('Fin quarantaine')
                                            ->date('d/m/Y')
                                            ->placeholder('—'),
                                    ]),

                                Section::make('Résolution')
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('resolution_date')
                                            ->label('Date de résolution')
                                            ->date('d/m/Y')
                                            ->placeholder('Non résolu'),

                                        TextEntry::make('resolution_notes')
                                            ->label('Notes de résolution')
                                            ->placeholder('—')
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('Amélioration continue')
                                    ->schema([
                                        TextEntry::make('lessons_learned')
                                            ->label('Leçons apprises')
                                            ->placeholder('—')
                                            ->columnSpanFull(),

                                        TextEntry::make('preventive_measures')
                                            ->label('Mesures préventives')
                                            ->placeholder('—')
                                            ->columnSpanFull(),

                                        TextEntry::make('notes')
                                            ->label('Notes internes')
                                            ->placeholder('—')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
