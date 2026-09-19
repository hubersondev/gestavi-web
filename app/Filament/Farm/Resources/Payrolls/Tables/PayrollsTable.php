<?php

namespace App\Filament\Farm\Resources\Payrolls\Tables;

use App\Enums\PayrollStatus;
use App\Filament\Farm\Resources\Payrolls\PayrollResource;
use App\Services\PayrollService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PayrollsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.full_name')
                    ->label('Employé')
                    ->state(fn ($record) => $record->employee?->full_name)
                    ->searchable(['employees.first_name', 'employees.last_name'])
                    ->sortable(),

                TextColumn::make('period')
                    ->label('Période')
                    ->state(fn ($record) => $record->getPeriodLabel()),

                TextColumn::make('gross_salary')
                    ->label('Salaire brut')
                    ->numeric()
                    ->suffix(' FCFA')
                    ->sortable(),

                TextColumn::make('cnps_employee')
                    ->label('CNPS salarié')
                    ->numeric()
                    ->suffix(' FCFA')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('net_salary')
                    ->label('Net à payer')
                    ->numeric()
                    ->suffix(' FCFA')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn ($state) => $state->color())
                    ->sortable(),
            ])
            ->defaultSort('period_year', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(PayrollStatus::class),

                SelectFilter::make('period_year')
                    ->label('Année')
                    ->options(function () {
                        $currentYear = now()->year;

                        return collect(range($currentYear, $currentYear - 5))
                            ->mapWithKeys(fn ($year) => [$year => $year])
                            ->toArray();
                    }),

                SelectFilter::make('period_month')
                    ->label('Mois')
                    ->options([
                        1 => 'Janvier',
                        2 => 'Février',
                        3 => 'Mars',
                        4 => 'Avril',
                        5 => 'Mai',
                        6 => 'Juin',
                        7 => 'Juillet',
                        8 => 'Août',
                        9 => 'Septembre',
                        10 => 'Octobre',
                        11 => 'Novembre',
                        12 => 'Décembre',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),

                EditAction::make()
                    ->visible(fn ($record) => $record->isDraft() && PayrollResource::canEdit($record)),

                Action::make('validate')
                    ->label('Valider')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Valider le bulletin de paie')
                    ->modalDescription('Le bulletin passera en statut "Validé". Confirmez-vous ?')
                    ->visible(fn ($record) => $record->isDraft() && PayrollResource::canPerformAction('validate'))
                    ->action(fn ($record) => app(PayrollService::class)->validatePayroll($record, auth()->id())),

                Action::make('mark_paid')
                    ->label('Marquer payé')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Marquer comme payé')
                    ->modalDescription('Le bulletin passera en statut "Payé". Confirmez-vous ?')
                    ->visible(fn ($record) => $record->isValidated() && PayrollResource::canPerformAction('mark_paid'))
                    ->action(fn ($record) => $record->markAsPaid(auth()->id())),

                Action::make('cancel')
                    ->label('Annuler')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Annuler le bulletin de paie')
                    ->modalDescription('Le bulletin sera annulé. Cette action est irréversible.')
                    ->visible(fn ($record) => ! $record->isPaid() && PayrollResource::canPerformAction('cancel'))
                    ->action(fn ($record) => $record->cancel()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => PayrollResource::canDeleteAny())
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                if ($record->isDraft()) {
                                    $record->delete();
                                }
                            });
                        }),
                ]),
            ]);
    }
}
