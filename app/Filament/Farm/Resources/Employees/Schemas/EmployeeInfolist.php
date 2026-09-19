<?php

namespace App\Filament\Farm\Resources\Employees\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class EmployeeInfolist
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
                                Section::make('Informations générales')
                                    ->columns(3)
                                    ->schema([
                                        TextEntry::make('full_name')
                                            ->label('Nom complet')
                                            ->weight('bold')
                                            ->state(fn ($record) => $record->full_name),

                                        TextEntry::make('employee_number')
                                            ->label('Matricule')
                                            ->placeholder('-'),

                                        TextEntry::make('position')
                                            ->label('Poste')
                                            ->placeholder('-'),

                                        TextEntry::make('department')
                                            ->label('Département')
                                            ->placeholder('-'),

                                        TextEntry::make('farm.name')
                                            ->label('Ferme')
                                            ->placeholder('-'),

                                        TextEntry::make('status')
                                            ->label('Statut')
                                            ->badge()
                                            ->color(fn ($state) => $state->color()),
                                    ]),

                                Section::make('Dates')
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('hire_date')
                                            ->label("Date d'embauche")
                                            ->date('d/m/Y')
                                            ->placeholder('-'),

                                        TextEntry::make('termination_date')
                                            ->label('Date de résiliation')
                                            ->date('d/m/Y')
                                            ->placeholder('-'),
                                    ]),

                                Section::make('Contacts')
                                    ->columns(3)
                                    ->schema([
                                        TextEntry::make('phone')
                                            ->label('Téléphone')
                                            ->placeholder('-'),

                                        TextEntry::make('email')
                                            ->label('Email')
                                            ->placeholder('-'),

                                        TextEntry::make('address')
                                            ->label('Adresse')
                                            ->placeholder('-'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Contrat actuel')
                            ->icon('heroicon-o-document-check')
                            ->schema([
                                Section::make('Détails du contrat')
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('activeContract.base_salary')
                                            ->label('Salaire de base')
                                            ->money('XOF')
                                            ->placeholder('Aucun contrat actif'),

                                        TextEntry::make('activeContract.type')
                                            ->label('Type de contrat')
                                            ->badge()
                                            ->color('info')
                                            ->placeholder('—'),

                                        TextEntry::make('activeContract.start_date')
                                            ->label('Début du contrat')
                                            ->date('d/m/Y')
                                            ->placeholder('—'),

                                        TextEntry::make('activeContract.end_date')
                                            ->label('Fin du contrat')
                                            ->date('d/m/Y')
                                            ->placeholder('CDI / Pas de fin'),

                                        TextEntry::make('activeContract.gross_salary')
                                            ->label('Package total (brut)')
                                            ->money('XOF')
                                            ->placeholder('—'),
                                    ]),
                            ]),

                        Tabs\Tab::make("Coordonnées d'urgence")
                            ->icon('heroicon-o-phone-arrow-up-right')
                            ->schema([
                                Section::make("Contact d'urgence")
                                    ->columns(3)
                                    ->schema([
                                        TextEntry::make('emergency_contact_name')
                                            ->label('Nom')
                                            ->placeholder('-'),

                                        TextEntry::make('emergency_contact_phone')
                                            ->label('Téléphone')
                                            ->placeholder('-'),

                                        TextEntry::make('emergency_contact_relationship')
                                            ->label('Lien de parenté')
                                            ->placeholder('-'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Notes')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        TextEntry::make('notes')
                                            ->label('Observations')
                                            ->placeholder('Aucune note')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
