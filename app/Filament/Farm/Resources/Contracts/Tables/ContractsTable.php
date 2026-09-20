<?php

namespace App\Filament\Farm\Resources\Contracts\Tables;

use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Enums\EmployeeStatus;
use App\Filament\Farm\Resources\Contracts\ContractResource;
use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Services\PayrollService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee_name')
                    ->label('Employé')
                    ->state(fn ($record): string => $record->employee?->full_name ?? '—')
                    ->searchable(query: fn ($query, $search) => $query->whereHas(
                        'employee',
                        fn ($q) => $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                    ))
                    ->sortable(query: fn ($query, $direction) => $query->join('employees', 'contracts.employee_id', '=', 'employees.id')
                        ->orderBy('employees.last_name', $direction)),

                TextColumn::make('contract_number')
                    ->label('N° Contrat')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($state) => $state->color())
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn ($state) => $state->color())
                    ->sortable(),

                TextColumn::make('start_date')
                    ->label('Début')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('Fin')
                    ->date('d/m/Y')
                    ->placeholder('Indéterminé')
                    ->sortable(),

                TextColumn::make('base_salary')
                    ->label('Salaire de base')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' FCFA')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('start_date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(ContractStatus::class),

                SelectFilter::make('type')
                    ->label('Type')
                    ->options(ContractType::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn ($record) => ContractResource::canEdit($record)),

                Action::make('download_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function ($record): \Symfony\Component\HttpFoundation\StreamedResponse {
                        $contract = $record->load('employee');
                        $employee = $contract->employee;
                        $organisation = \Filament\Facades\Filament::getTenant();

                        $service = app(PayrollService::class);
                        $grossSalary = (float) $contract->base_salary
                            + (float) $contract->transport_allowance
                            + (float) $contract->housing_allowance
                            + (float) $contract->food_allowance;
                        $cnps = $service->calculateCnpsEmployee($grossSalary);
                        $its = $service->calculateIts($grossSalary - $cnps);
                        $netSalary = $grossSalary - $cnps - $its;

                        $pdf = Pdf::loadView('pdf.contract', compact(
                            'contract', 'employee', 'organisation',
                            'grossSalary', 'cnps', 'its', 'netSalary'
                        ))->setPaper('a4', 'portrait');

                        $filename = 'contrat-'.($contract->contract_number ?? $contract->id).'.pdf';

                        return response()->streamDownload(
                            fn () => print ($pdf->output()),
                            $filename,
                            ['Content-Type' => 'application/pdf']
                        );
                    }),

                Action::make('renew')
                    ->label('Renouveler')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->modalHeading('Renouveler le contrat')
                    ->modalDescription('Un nouveau contrat sera créé sur la base de celui-ci. L\'ancien passera en statut "Expiré".')
                    ->form(fn ($record): array => [
                        Select::make('type')
                            ->label('Type de contrat')
                            ->options(ContractType::class)
                            ->default($record->type)
                            ->required()
                            ->native(false)
                            ->live(),

                        DatePicker::make('start_date')
                            ->label('Nouvelle date de début')
                            ->default($record->end_date?->addDay() ?? now())
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        DatePicker::make('end_date')
                            ->label('Nouvelle date de fin')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->hidden(fn ($get) => $get('type') === ContractType::Cdi->value)
                            ->helperText('Obligatoire pour CDD, saisonnier, stage'),
                    ])
                    ->visible(fn ($record) => $record->status === ContractStatus::Active
                        && ContractResource::canPerformAction('renew'))
                    ->action(function ($record, array $data): void {
                        // Expirer l'ancien contrat
                        $record->update(['status' => ContractStatus::Expired]);

                        // Créer le nouveau contrat en copiant les données clés
                        Contract::create([
                            'employee_id' => $record->employee_id,
                            'renewed_by_contract_id' => $record->id,
                            'type' => $data['type'],
                            'status' => ContractStatus::Active,
                            'start_date' => $data['start_date'],
                            'end_date' => $data['end_date'] ?? null,
                            'base_salary' => $record->base_salary,
                            'category_salary' => $record->category_salary,
                            'professional_category' => $record->professional_category,
                            'transport_allowance' => $record->transport_allowance,
                            'housing_allowance' => $record->housing_allowance,
                            'food_allowance' => $record->food_allowance,
                            'working_hours_per_week' => $record->working_hours_per_week,
                            'annual_leave_days' => $record->annual_leave_days,
                            'position' => $record->position,
                            'work_location' => $record->work_location,
                        ]);

                        Notification::make()
                            ->title('Contrat renouvelé')
                            ->body('Le nouveau contrat a été créé et l\'ancien est passé en statut "Expiré".')
                            ->success()
                            ->send();
                    }),

                Action::make('amend')
                    ->label('Avenant')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->modalHeading('Créer un avenant')
                    ->modalDescription('Les modifications seront appliquées au contrat et tracées dans l\'historique des avenants.')
                    ->form(fn ($record): array => [
                        DatePicker::make('effective_date')
                            ->label('Date d\'effet')
                            ->default(now())
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        Select::make('reason')
                            ->label('Motif')
                            ->options([
                                'salary_increase' => 'Augmentation de salaire',
                                'salary_decrease' => 'Réduction de salaire',
                                'position_change' => 'Changement de poste',
                                'location_change' => 'Changement de lieu de travail',
                                'hours_change' => 'Modification des horaires',
                                'allowance_change' => 'Modification des indemnités',
                                'other' => 'Autre',
                            ])
                            ->required()
                            ->native(false),

                        TextInput::make('base_salary')
                            ->label('Salaire de base')
                            ->numeric()
                            ->default(fn () => $record->base_salary)
                            ->suffix('FCFA'),

                        TextInput::make('transport_allowance')
                            ->label('Indemnité de transport')
                            ->numeric()
                            ->default(fn () => $record->transport_allowance)
                            ->suffix('FCFA'),

                        TextInput::make('housing_allowance')
                            ->label('Indemnité de logement')
                            ->numeric()
                            ->default(fn () => $record->housing_allowance)
                            ->suffix('FCFA'),

                        TextInput::make('food_allowance')
                            ->label('Indemnité de repas')
                            ->numeric()
                            ->default(fn () => $record->food_allowance)
                            ->suffix('FCFA'),

                        TextInput::make('position')
                            ->label('Poste')
                            ->default(fn () => $record->position)
                            ->maxLength(255),

                        TextInput::make('work_location')
                            ->label('Lieu de travail')
                            ->default(fn () => $record->work_location)
                            ->maxLength(255),

                        TextInput::make('working_hours_per_week')
                            ->label('Heures par semaine')
                            ->numeric()
                            ->default(fn () => $record->working_hours_per_week)
                            ->suffix('h'),

                        Textarea::make('notes')
                            ->label('Notes / clauses particulières')
                            ->rows(3),
                    ])
                    ->visible(fn ($record) => $record->status === ContractStatus::Active
                        && ContractResource::canPerformAction('update'))
                    ->action(function ($record, array $data): void {
                        $amendableFields = [
                            'base_salary', 'transport_allowance', 'housing_allowance',
                            'food_allowance', 'position', 'work_location', 'working_hours_per_week',
                        ];

                        // Capturer uniquement les champs qui ont changé
                        $previousValues = [];
                        $newValues = [];

                        foreach ($amendableFields as $field) {
                            $oldVal = (string) ($record->$field ?? '');
                            $newVal = (string) ($data[$field] ?? '');

                            if ($oldVal !== $newVal) {
                                $previousValues[$field] = $record->$field;
                                $newValues[$field] = $data[$field];
                            }
                        }

                        // Appliquer les modifications au contrat
                        $record->update(array_merge(
                            array_intersect_key($data, array_flip($amendableFields))
                        ));

                        // Enregistrer l'avenant
                        ContractAmendment::create([
                            'contract_id' => $record->id,
                            'amendment_number' => ContractAmendment::generateNumber($record->id),
                            'effective_date' => $data['effective_date'],
                            'reason' => $data['reason'],
                            'previous_values' => $previousValues ?: ['aucun_changement' => true],
                            'new_values' => $newValues ?: ['aucun_changement' => true],
                            'notes' => $data['notes'] ?? null,
                        ]);

                        Notification::make()
                            ->title('Avenant créé')
                            ->body('Le contrat a été mis à jour et l\'avenant '.ContractAmendment::where('contract_id', $record->id)->latest()->first()?->amendment_number.' a été enregistré.')
                            ->success()
                            ->send();
                    }),

                Action::make('suspend')
                    ->label('Suspendre')
                    ->icon('heroicon-o-pause-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading("Suspendre l'employé")
                    ->modalDescription("L'employé passera en statut \"Suspendu\". Le contrat reste actif.")
                    ->visible(fn ($record) => $record->status === ContractStatus::Active
                        && $record->employee?->status === EmployeeStatus::Active
                        && ContractResource::canPerformAction('suspend'))
                    ->action(fn ($record) => $record->employee?->suspend()),

                Action::make('reactivate')
                    ->label('Réactiver')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading("Réactiver l'employé")
                    ->modalDescription("L'employé repassera en statut \"Actif\".")
                    ->visible(fn ($record) => $record->status === ContractStatus::Active
                        && $record->employee?->status === EmployeeStatus::Suspended
                        && ContractResource::canPerformAction('reactivate'))
                    ->action(fn ($record) => $record->employee?->reactivate()),

                Action::make('terminate')
                    ->label('Résilier')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Résilier le contrat')
                    ->modalDescription('Le contrat passera en statut "Annulé" et l\'employé en statut "Résilié".')
                    ->form([
                        DatePicker::make('termination_date')
                            ->label('Date de résiliation')
                            ->default(now())
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        Textarea::make('termination_reason')
                            ->label('Motif de résiliation')
                            ->required()
                            ->rows(3),
                    ])
                    ->visible(fn ($record) => $record->status === ContractStatus::Active
                        && ContractResource::canPerformAction('terminate'))
                    ->action(function ($record, array $data): void {
                        $record->update([
                            'status' => ContractStatus::Cancelled,
                            'termination_date' => $data['termination_date'],
                            'termination_reason' => $data['termination_reason'],
                        ]);

                        $record->employee?->terminate(
                            $data['termination_reason'],
                            \Carbon\Carbon::parse($data['termination_date'])
                        );
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => ContractResource::canDeleteAny()),
                ]),
            ]);
    }
}
