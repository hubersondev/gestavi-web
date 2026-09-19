<?php

namespace App\Filament\Farm\Resources\Vaccinations\Schemas;

use App\Enums\VaccinationStatus;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class VaccinationInfolist
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

                                        TextEntry::make('healthProgram.treatment_name')
                                            ->label('Programme sanitaire')
                                            ->placeholder('—'),
                                    ]),

                                Section::make('Traitement')
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('treatment_name')
                                            ->label('Nom du traitement')
                                            ->weight('bold'),

                                        TextEntry::make('treatment_type')
                                            ->label('Type')
                                            ->badge()
                                            ->color(fn ($state) => match ($state) {
                                                'vaccin' => 'info',
                                                'antibiotique' => 'danger',
                                                'vitamine' => 'success',
                                                'vermifuge', 'anticoccidien', 'antiparasitaire' => 'warning',
                                                default => 'gray',
                                            })
                                            ->formatStateUsing(fn ($state) => VaccinationForm::treatmentTypeOptions()[$state] ?? $state),

                                        TextEntry::make('administration_route')
                                            ->label("Voie d'administration")
                                            ->formatStateUsing(fn ($state) => VaccinationForm::administrationRouteOptions()[$state] ?? $state)
                                            ->placeholder('—'),

                                        TextEntry::make('dosage')
                                            ->label('Dosage')
                                            ->placeholder('—'),

                                        TextEntry::make('status')
                                            ->label('Statut')
                                            ->badge()
                                            ->color(fn (VaccinationStatus $state) => $state->color())
                                            ->icon(fn (VaccinationStatus $state) => $state->icon())
                                            ->formatStateUsing(fn (VaccinationStatus $state) => $state->label()),
                                    ]),
                            ]),

                        Tabs\Tab::make('Dates & Résultat')
                            ->icon('heroicon-o-calendar')
                            ->schema([
                                Section::make('Dates')
                                    ->columns(3)
                                    ->schema([
                                        TextEntry::make('scheduled_date')
                                            ->label('Date prévue')
                                            ->date('d/m/Y'),

                                        TextEntry::make('administered_date')
                                            ->label('Date réalisée')
                                            ->date('d/m/Y')
                                            ->placeholder('Non effectuée'),

                                        TextEntry::make('age_at_treatment')
                                            ->label('Âge prévu')
                                            ->suffix(' jours')
                                            ->placeholder('—'),

                                        TextEntry::make('administrator.name')
                                            ->label('Administrateur')
                                            ->placeholder('—'),

                                        TextEntry::make('birds_treated')
                                            ->label('Sujets traités')
                                            ->suffix(' sujets')
                                            ->placeholder('—'),
                                    ]),

                                Section::make('Observations')
                                    ->schema([
                                        TextEntry::make('observations')
                                            ->label('Observations')
                                            ->placeholder('Aucune observation')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Produit & Coût')
                            ->icon('heroicon-o-beaker')
                            ->schema([
                                Section::make('Produit')
                                    ->columns(3)
                                    ->schema([
                                        TextEntry::make('batch_number')
                                            ->label('Numéro de lot')
                                            ->placeholder('—'),

                                        TextEntry::make('expiry_date')
                                            ->label("Date d'expiration")
                                            ->date('d/m/Y')
                                            ->color(fn ($record) => $record->expiry_date?->isPast() ? 'danger' : null)
                                            ->placeholder('—'),

                                        TextEntry::make('cost')
                                            ->label('Coût total')
                                            ->money('XOF')
                                            ->placeholder('—'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Notes')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        TextEntry::make('notes')
                                            ->label('Notes internes')
                                            ->placeholder('Aucune note')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
