<?php

namespace App\Filament\Farm\Resources\Payrolls\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PayrollInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('En-tête')
                    ->schema([
                        TextEntry::make('employee.full_name')
                            ->label('Employé')
                            ->weight('bold'),

                        TextEntry::make('period_label')
                            ->label('Période')
                            ->state(fn ($record) => $record->getPeriodLabel()),

                        TextEntry::make('status')
                            ->label('Statut')
                            ->badge()
                            ->color(fn ($state) => $state->color()),
                    ])
                    ->columns(3),

                Section::make('Rémunération')
                    ->schema([
                        TextEntry::make('gross_salary')
                            ->label('Total des gains')
                            ->money('XOF'),

                        TextEntry::make('taxable_gross')
                            ->label('Brut imposable')
                            ->money('XOF')
                            ->helperText('Gains hors prime de transport exonérée'),

                        TextEntry::make('cnps_employee')
                            ->label('CNPS salarié')
                            ->money('XOF'),

                        TextEntry::make('cnps_employer')
                            ->label('CNPS employeur')
                            ->money('XOF')
                            ->helperText(fn ($record): string => $record->employer_contributions
                                ? 'Total charges patronales (CNPS, FDFP, CMU) : '.number_format((float) ($record->employer_contributions['total'] ?? 0), 0, ',', ' ').' XOF'
                                : 'Charge patronale (informatif)'),

                        TextEntry::make('its')
                            ->label('ITS')
                            ->money('XOF'),

                        TextEntry::make('cmu_employee')
                            ->label('CMU salarié')
                            ->money('XOF'),

                        TextEntry::make('other_deductions')
                            ->label('Autres retenues')
                            ->money('XOF'),

                        TextEntry::make('total_deductions')
                            ->label('Total retenues')
                            ->money('XOF'),

                        TextEntry::make('net_salary')
                            ->label('Salaire net')
                            ->money('XOF')
                            ->weight('bold')
                            ->extraAttributes(['class' => 'text-lg']),
                    ])
                    ->columns(3),

                Section::make('Présences')
                    ->schema([
                        TextEntry::make('worked_days')
                            ->label('Jours travaillés')
                            ->suffix(' jours')
                            ->placeholder('—'),

                        TextEntry::make('leave_days')
                            ->label('Jours de congé')
                            ->suffix(' jours'),

                        TextEntry::make('overtime_hours')
                            ->label('Heures supplémentaires')
                            ->suffix(' h'),
                    ])
                    ->columns(3),

                Section::make('Validation & Paiement')
                    ->schema([
                        TextEntry::make('validator.name')
                            ->label('Validé par')
                            ->placeholder('—'),

                        TextEntry::make('validated_at')
                            ->label('Validé le')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),

                        TextEntry::make('payer.name')
                            ->label('Payé par')
                            ->placeholder('—'),

                        TextEntry::make('paid_at')
                            ->label('Payé le')
                            ->date('d/m/Y')
                            ->placeholder('—'),

                        TextEntry::make('expense.expense_number')
                            ->label('Dépense comptabilisée')
                            ->placeholder('—'),

                        TextEntry::make('parameter_label')
                            ->label('Paramétrage appliqué')
                            ->state(fn ($record) => $record->parameter?->getLabel() ?? 'Barème légal embarqué')
                            ->helperText(fn ($record) => $record->parameter?->source),
                    ])
                    ->columns(2),
            ]);
    }
}
