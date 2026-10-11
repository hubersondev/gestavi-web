<?php

namespace App\Filament\Farm\Resources\VetVisits\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VetVisitInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations générales')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('visit_date')
                            ->label('Date')
                            ->date('d/m/Y'),

                        TextEntry::make('visit_time')
                            ->label('Heure')
                            ->time('H:i')
                            ->placeholder('-'),

                        TextEntry::make('visit_type')
                            ->label('Type')
                            ->badge(),

                        TextEntry::make('farm.name')
                            ->label('Ferme'),

                        TextEntry::make('flock.code')
                            ->label('Bande')
                            ->placeholder('Toute la ferme')
                            ->helperText(fn ($record) => $record->flock?->name),

                        TextEntry::make('veterinarian.name')
                            ->label('Vétérinaire')
                            ->helperText(fn ($record) => $record->veterinarian?->clinic),

                        TextEntry::make('reason')
                            ->label('Motif')
                            ->columnSpanFull(),
                    ]),

                Section::make('Observations cliniques')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('symptoms_observed')
                            ->label('Symptômes observés')
                            ->placeholder('Aucun symptôme renseigné.')
                            ->columnSpanFull(),

                        TextEntry::make('birds_examined')
                            ->label('Sujets examinés')
                            ->numeric()
                            ->placeholder('-'),

                        TextEntry::make('birds_affected')
                            ->label('Sujets affectés')
                            ->numeric()
                            ->placeholder('-'),

                        TextEntry::make('affected_rate')
                            ->label("Taux d'affection")
                            ->state(fn ($record) => $record->getAffectedRate() !== null ? $record->getAffectedRate().' %' : null)
                            ->placeholder('-'),
                    ]),

                Section::make('Diagnostic')
                    ->schema([
                        TextEntry::make('diagnosis')
                            ->label('Diagnostic')
                            ->placeholder('Aucun diagnostic renseigné.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Prélèvements & Résultats')
                    ->columns(1)
                    ->schema([
                        IconEntry::make('samples_taken')
                            ->label('Prélèvements effectués')
                            ->boolean(),

                        TextEntry::make('samples_description')
                            ->label('Description des prélèvements')
                            ->placeholder('-')
                            ->columnSpanFull()
                            ->hidden(fn ($record) => ! $record->samples_taken),

                        TextEntry::make('lab_results')
                            ->label('Résultats de laboratoire')
                            ->placeholder('-')
                            ->columnSpanFull()
                            ->hidden(fn ($record) => ! $record->samples_taken),
                    ]),

                Section::make('Traitement & Recommandations')
                    ->schema([
                        TextEntry::make('prescriptions')
                            ->label('Prescriptions / Traitements')
                            ->placeholder('Aucune prescription.')
                            ->columnSpanFull(),

                        TextEntry::make('recommendations')
                            ->label('Recommandations')
                            ->placeholder('Aucune recommandation.')
                            ->columnSpanFull(),

                        TextEntry::make('preventive_measures')
                            ->label('Mesures préventives')
                            ->placeholder('Aucune mesure préventive.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Financier')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('consultation_fee')
                            ->label('Honoraires')
                            ->money('XOF')
                            ->placeholder('-'),

                        TextEntry::make('medication_cost')
                            ->label('Médicaments')
                            ->money('XOF')
                            ->placeholder('-'),

                        TextEntry::make('total_cost')
                            ->label('Total')
                            ->money('XOF')
                            ->weight('bold')
                            ->placeholder('-'),

                        IconEntry::make('is_paid')
                            ->label('Payé')
                            ->boolean(),

                        TextEntry::make('paid_at')
                            ->label('Date de paiement')
                            ->date('d/m/Y')
                            ->placeholder('-')
                            ->hidden(fn ($record) => ! $record->is_paid),
                    ]),

                Section::make('Suivi')
                    ->columns(2)
                    ->schema([
                        IconEntry::make('follow_up_required')
                            ->label('Suivi requis')
                            ->boolean(),

                        TextEntry::make('next_visit_date')
                            ->label('Prochaine visite')
                            ->date('d/m/Y')
                            ->placeholder('-'),

                        TextEntry::make('days_until_next_visit')
                            ->label('Dans')
                            ->state(fn ($record) => $record->getDaysUntilNextVisit() !== null
                                ? $record->getDaysUntilNextVisit().' jours'
                                : null)
                            ->placeholder('-')
                            ->color(fn ($record) => match (true) {
                                $record->getDaysUntilNextVisit() !== null && $record->getDaysUntilNextVisit() < 0 => 'danger',
                                $record->getDaysUntilNextVisit() !== null && $record->getDaysUntilNextVisit() <= 3 => 'warning',
                                default => null,
                            }),
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

                        TextEntry::make('parentVisit.visit_date')
                            ->label('Visite parente')
                            ->date('d/m/Y')
                            ->placeholder('-'),

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
