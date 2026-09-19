<?php

namespace App\Filament\Farm\Resources\Payrolls\Schemas;

use App\Enums\EmployeeStatus;
use App\Enums\PayrollStatus;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\Payroll;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PayrollForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Paie')
                    ->tabs([

                        // ─── Onglet Informations générales ────────────────────
                        Tabs\Tab::make('Informations générales')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Section::make('Employé & Contrat')
                                    ->schema([
                                        Select::make('employee_id')
                                            ->label('Employé')
                                            ->options(function (): array {
                                                $tenant = Filament::getTenant();

                                                return Employee::query()
                                                    ->where('organisation_id', $tenant?->getKey())
                                                    ->tap(fn ($q) => app(CurrentFarm::class)->restrictForCurrentUser($q))
                                                    ->where('status', '!=', EmployeeStatus::Terminated->value)
                                                    ->orderBy('last_name')
                                                    ->orderBy('first_name')
                                                    ->get()
                                                    ->mapWithKeys(fn (Employee $e) => [
                                                        $e->id => "{$e->employee_number} – {$e->full_name}",
                                                    ])
                                                    ->toArray();
                                            })
                                            ->required()
                                            ->searchable()
                                            ->live()
                                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                                $employeeId = $get('employee_id');

                                                if (! $employeeId) {
                                                    $set('contract_id', null);

                                                    return;
                                                }

                                                $activeContract = Contract::where('employee_id', $employeeId)
                                                    ->where('status', 'active')
                                                    ->latest('start_date')
                                                    ->first();

                                                $set('contract_id', $activeContract?->id);
                                            }),

                                        Select::make('contract_id')
                                            ->label('Contrat')
                                            ->options(fn (Get $get): array => Contract::where('employee_id', $get('employee_id'))
                                                ->where('status', 'active')
                                                ->get()
                                                ->mapWithKeys(fn (Contract $c) => [
                                                    $c->id => $c->contract_number ?? 'Contrat #'.$c->id,
                                                ])
                                                ->toArray())
                                            ->required()
                                            ->searchable()
                                            ->visible(fn (Get $get): bool => (bool) $get('employee_id'))
                                            ->helperText('Le contrat actif est sélectionné automatiquement.'),

                                        Placeholder::make('contract_salary_info')
                                            ->label('Salaire contractuel')
                                            ->content(function (Get $get): string {
                                                $employeeId = $get('employee_id');

                                                if (! $employeeId) {
                                                    return '—';
                                                }

                                                $employee = Employee::with('activeContract')->find($employeeId);
                                                $contract = $employee?->activeContract;

                                                if (! $contract) {
                                                    return 'Aucun contrat actif';
                                                }

                                                $brut = $contract->base_salary
                                                    + ($contract->transport_allowance ?? 0)
                                                    + ($contract->housing_allowance ?? 0)
                                                    + ($contract->food_allowance ?? 0);

                                                return 'Base : '.number_format($contract->base_salary, 0, ',', ' ')
                                                    .' | Brut : '.number_format($brut, 0, ',', ' ').' FCFA';
                                            })
                                            ->visible(fn (Get $get): bool => (bool) $get('employee_id')),
                                    ])
                                    ->columns(1),

                                Section::make('Période')
                                    ->schema([
                                        TextInput::make('period_year')
                                            ->label('Année')
                                            ->numeric()
                                            ->required()
                                            ->default(fn () => now()->year)
                                            ->minValue(2020),

                                        Select::make('period_month')
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
                                            ])
                                            ->required()
                                            ->default(fn () => now()->month)
                                            ->native(false)
                                            ->rules([
                                                fn (Get $get, $record): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get, $record): void {
                                                    $employeeId = $get('employee_id');
                                                    $year = $get('period_year');

                                                    if (! $employeeId || ! $year || ! $value) {
                                                        return;
                                                    }

                                                    $exists = Payroll::where('employee_id', $employeeId)
                                                        ->where('period_year', $year)
                                                        ->where('period_month', $value)
                                                        ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                                        ->exists();

                                                    if ($exists) {
                                                        $fail('Un bulletin de paie existe déjà pour cet employé sur cette période.');
                                                    }
                                                },
                                            ]),

                                        Select::make('status')
                                            ->label('Statut')
                                            ->options(PayrollStatus::class)
                                            ->default(PayrollStatus::Draft)
                                            ->disabled()
                                            ->native(false),
                                    ])
                                    ->columns(3),

                                Section::make('Jours & Heures')
                                    ->schema([
                                        TextInput::make('worked_days')
                                            ->label('Jours travaillés')
                                            ->numeric()
                                            ->nullable(),

                                        TextInput::make('leave_days')
                                            ->label('Jours de congé')
                                            ->numeric()
                                            ->default(0),

                                        TextInput::make('overtime_hours')
                                            ->label('Heures supplémentaires')
                                            ->numeric()
                                            ->default(0)
                                            ->suffix('h'),
                                    ])
                                    ->columns(3),
                            ]),

                        // ─── Onglet Ajustements ───────────────────────────────
                        Tabs\Tab::make('Ajustements')
                            ->icon('heroicon-o-adjustments-horizontal')
                            ->schema([
                                Section::make('Retenues supplémentaires')
                                    ->description('Les cotisations CNPS et ITS sont calculées automatiquement à la validation depuis le contrat.')
                                    ->schema([
                                        TextInput::make('other_deductions')
                                            ->label('Autres retenues')
                                            ->numeric()
                                            ->default(0)
                                            ->suffix('FCFA')
                                            ->helperText('Avances, saisies sur salaire, absences non justifiées…'),
                                    ]),
                            ]),

                        // ─── Onglet Notes ─────────────────────────────────────
                        Tabs\Tab::make('Notes')
                            ->icon('heroicon-o-pencil-square')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        Textarea::make('notes')
                                            ->label('Notes')
                                            ->rows(5)
                                            ->placeholder('Notes complémentaires…')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                    ])
                    ->columnSpanFull(),
            ]);
    }
}
