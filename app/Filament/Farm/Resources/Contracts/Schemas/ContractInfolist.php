<?php

namespace App\Filament\Farm\Resources\Contracts\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContractInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Employé & Contrat')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('employee.full_name')
                            ->label('Employé')
                            ->weight('bold'),

                        TextEntry::make('type')
                            ->label('Type')
                            ->badge()
                            ->color(fn ($state) => $state->color()),

                        TextEntry::make('status')
                            ->label('Statut')
                            ->badge()
                            ->color(fn ($state) => $state->color()),

                        TextEntry::make('contract_number')
                            ->label('Numéro')
                            ->placeholder('—'),

                        TextEntry::make('position')
                            ->label('Poste')
                            ->placeholder('—'),

                        TextEntry::make('professional_category')
                            ->label('Catégorie')
                            ->placeholder('—'),

                        TextEntry::make('work_location')
                            ->label('Lieu de travail')
                            ->placeholder('—'),
                    ]),

                Section::make('Dates')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('start_date')
                            ->label('Date de début')
                            ->date('d/m/Y'),

                        TextEntry::make('end_date')
                            ->label('Date de fin')
                            ->date('d/m/Y')
                            ->placeholder('Indéterminé'),

                        TextEntry::make('days_until_expiry')
                            ->label('Jours avant expiration')
                            ->state(fn ($record): ?string => $record->isCdd() && $record->getDaysUntilExpiry() !== null
                                ? $record->getDaysUntilExpiry().' jours'
                                : null)
                            ->placeholder('—')
                            ->hidden(fn ($record): bool => ! $record->isCdd()),
                    ]),

                Section::make('Rémunération')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('base_salary')
                            ->label('Salaire de base')
                            ->money('XOF'),

                        TextEntry::make('transport_allowance')
                            ->label('Indemnité transport')
                            ->money('XOF')
                            ->placeholder('—'),

                        TextEntry::make('housing_allowance')
                            ->label('Indemnité logement')
                            ->money('XOF')
                            ->placeholder('—'),

                        TextEntry::make('food_allowance')
                            ->label('Indemnité repas')
                            ->money('XOF')
                            ->placeholder('—'),

                        TextEntry::make('total_package')
                            ->label('Package total')
                            ->state(fn ($record): string => number_format($record->getTotalPackage(), 0, ',', ' ').' FCFA')
                            ->weight('bold'),
                    ]),

                Section::make('Conditions')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('working_hours_per_week')
                            ->label('Heures par semaine')
                            ->suffix(' h'),

                        TextEntry::make('annual_leave_days')
                            ->label('Jours de congé annuel')
                            ->suffix(' jours'),
                    ]),

                Section::make('Notes')
                    ->schema([
                        TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('Aucune description')
                            ->columnSpanFull(),

                        TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('Aucune note')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
