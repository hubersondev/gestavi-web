<?php

namespace App\Filament\Farm\Resources\Contracts\Schemas;

use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Enums\EmployeeStatus;
use App\Models\Contract;
use App\Models\Employee;
use App\Services\CurrentFarm;
use App\Services\PayrollService;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class ContractForm
{
    private static function updateGross(Get $get, Set $set): void
    {
        $gross = floatval($get('base_salary') ?? 0)
            + floatval($get('transport_allowance') ?? 0)
            + floatval($get('housing_allowance') ?? 0)
            + floatval($get('food_allowance') ?? 0);

        $service = app(PayrollService::class);

        $cnps = $service->calculateCnpsEmployee($gross);
        $its = $service->calculateIts($gross - $cnps);
        $net = $gross - $cnps - $its;

        $set('gross_salary_preview', number_format($gross, 0, ',', ' '));
        $set('cnps_preview', number_format($cnps, 0, ',', ' '));
        $set('its_preview', number_format($its, 0, ',', ' '));
        $set('net_salary_preview', number_format($net, 0, ',', ' '));
    }

    private static function updateFromNet(Get $get, Set $set): void
    {
        $targetNet = floatval($get('net_salary_input') ?? 0);

        if ($targetNet <= 0) {
            return;
        }

        $service = app(PayrollService::class);
        $gross = $service->grossFromNet($targetNet);

        $set('base_salary', $gross);

        // Recalculer le récapitulatif avec le brut dérivé + les indemnités existantes
        $totalGross = $gross
            + floatval($get('transport_allowance') ?? 0)
            + floatval($get('housing_allowance') ?? 0)
            + floatval($get('food_allowance') ?? 0);

        $cnps = $service->calculateCnpsEmployee($totalGross);
        $its = $service->calculateIts($totalGross - $cnps);
        $net = $totalGross - $cnps - $its;

        $set('gross_salary_preview', number_format($totalGross, 0, ',', ' '));
        $set('cnps_preview', number_format($cnps, 0, ',', ' '));
        $set('its_preview', number_format($its, 0, ',', ' '));
        $set('net_salary_preview', number_format($net, 0, ',', ' '));
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Contrat')
                    ->tabs([

                        // ─── Onglet Informations générales ────────────────────
                        Tabs\Tab::make('Informations générales')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make('Employé')
                                    ->schema([
                                        Select::make('employee_id')
                                            // Garde-fou serveur : la valeur soumise doit appartenir à l'organisation courante
                                            ->rules(fn (): array => [Rule::exists('employees', 'id')->where('organisation_id', Filament::getTenant()?->getKey())])
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
                                            ->rules([
                                                fn (Get $get, $record): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get, $record): void {
                                                    if (! $value) {
                                                        return;
                                                    }

                                                    if ($get('status') !== ContractStatus::Active->value) {
                                                        return;
                                                    }

                                                    $existing = Contract::where('employee_id', $value)
                                                        ->where('status', ContractStatus::Active)
                                                        ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                                        ->exists();

                                                    if ($existing) {
                                                        $fail('Cet employé possède déjà un contrat actif. Résiliez-le avant d\'en créer un nouveau.');
                                                    }
                                                },
                                            ]),

                                        Placeholder::make('employee_info')
                                            ->label('Situation actuelle')
                                            ->content(function ($get): string {
                                                $employeeId = $get('employee_id');

                                                if (! $employeeId) {
                                                    return 'Sélectionnez un employé';
                                                }

                                                $employee = Employee::with('activeContract')->find($employeeId);

                                                if (! $employee) {
                                                    return '—';
                                                }

                                                $active = $employee->activeContract;
                                                $contractInfo = $active
                                                    ? "Contrat actif : {$active->contract_number} | ".number_format($active->base_salary, 0, ',', ' ').' FCFA'
                                                    : 'Aucun contrat actif';

                                                return "Poste : {$employee->position} | {$contractInfo}";
                                            })
                                            ->visible(fn ($get): bool => (bool) $get('employee_id')),

                                        TextInput::make('contract_number')
                                            ->label('N° Contrat')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->placeholder('Généré automatiquement'),
                                    ])
                                    ->columns(1),

                                Section::make('Type & Statut')
                                    ->schema([
                                        Select::make('type')
                                            ->label('Type de contrat')
                                            ->options(ContractType::class)
                                            ->required()
                                            ->native(false)
                                            ->live(),

                                        Select::make('status')
                                            ->label('Statut')
                                            ->options(ContractStatus::class)
                                            ->default(ContractStatus::Active)
                                            ->required()
                                            ->native(false)
                                            ->live(),
                                    ])
                                    ->columns(2),

                                Section::make('Dates')
                                    ->schema([
                                        DatePicker::make('start_date')
                                            ->label('Date de début')
                                            ->required()
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->default(now()),

                                        DatePicker::make('end_date')
                                            ->label('Date de fin')
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->hidden(fn ($get): bool => $get('type') === ContractType::Cdi->value)
                                            ->helperText('Obligatoire pour CDD, saisonnier, stage'),
                                    ])
                                    ->columns(2),

                                Section::make('Poste')
                                    ->schema([
                                        TextInput::make('position')
                                            ->label('Poste')
                                            ->maxLength(255),

                                        TextInput::make('work_location')
                                            ->label('Lieu de travail')
                                            ->maxLength(255),
                                    ])
                                    ->columns(2),
                            ]),

                        // ─── Onglet Rémunération ──────────────────────────────
                        Tabs\Tab::make('Rémunération')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Section::make('Salaire')
                                    ->schema([
                                        Radio::make('salary_mode')
                                            ->label('Mode de saisie')
                                            ->options([
                                                'gross' => 'Saisir le salaire brut',
                                                'net' => 'Saisir le salaire net souhaité',
                                            ])
                                            ->default('gross')
                                            ->inline()
                                            ->live()
                                            ->dehydrated(false),

                                        TextInput::make('net_salary_input')
                                            ->label('Salaire net souhaité')
                                            ->numeric()
                                            ->default(0)
                                            ->suffix('FCFA')
                                            ->dehydrated(false)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn (Get $get, Set $set) => self::updateFromNet($get, $set))
                                            ->helperText('Le salaire brut sera calculé automatiquement.')
                                            ->visible(fn (Get $get): bool => $get('salary_mode') === 'net'),

                                        TextInput::make('base_salary')
                                            ->label(fn (Get $get): string => $get('salary_mode') === 'net' ? 'Salaire de base brut (calculé)' : 'Salaire de base (brut)')
                                            ->required()
                                            ->numeric()
                                            ->default(0)
                                            ->suffix('FCFA')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn (Get $get, Set $set) => self::updateGross($get, $set))
                                            ->disabled(fn (Get $get): bool => $get('salary_mode') === 'net')
                                            ->dehydrated(true),
                                    ]),

                                Section::make('Indemnités')
                                    ->schema([
                                        TextInput::make('transport_allowance')
                                            ->label('Indemnité de transport')
                                            ->numeric()
                                            ->default(0)
                                            ->suffix('FCFA')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn (Get $get, Set $set) => self::updateGross($get, $set)),

                                        TextInput::make('housing_allowance')
                                            ->label('Indemnité de logement')
                                            ->numeric()
                                            ->default(0)
                                            ->suffix('FCFA')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn (Get $get, Set $set) => self::updateGross($get, $set)),

                                        TextInput::make('food_allowance')
                                            ->label('Indemnité de repas')
                                            ->numeric()
                                            ->default(0)
                                            ->suffix('FCFA')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn (Get $get, Set $set) => self::updateGross($get, $set)),
                                    ])
                                    ->columns(3),

                                Section::make('Récapitulatif estimé')
                                    ->description('Estimation basée sur le barème CNPS et ITS en vigueur en Côte d\'Ivoire')
                                    ->schema([
                                        TextInput::make('gross_salary_preview')
                                            ->label('Salaire brut')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->default('0')
                                            ->suffix('FCFA'),

                                        TextInput::make('cnps_preview')
                                            ->label('CNPS salarié (6,3 %)')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->default('0')
                                            ->suffix('FCFA'),

                                        TextInput::make('its_preview')
                                            ->label('ITS')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->default('0')
                                            ->suffix('FCFA'),

                                        TextInput::make('net_salary_preview')
                                            ->label('Salaire net estimé')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->default('0')
                                            ->suffix('FCFA')
                                            ->extraAttributes(['class' => 'font-bold']),
                                    ])
                                    ->columns(2),
                            ]),

                        // ─── Onglet Congés & Horaires ─────────────────────────
                        Tabs\Tab::make('Congés & Horaires')
                            ->icon('heroicon-o-calendar')
                            ->schema([
                                Section::make('Temps de travail')
                                    ->schema([
                                        TextInput::make('working_hours_per_week')
                                            ->label('Heures par semaine')
                                            ->numeric()
                                            ->default(40)
                                            ->minValue(1)
                                            ->maxValue(60)
                                            ->suffix('h'),

                                        TextInput::make('annual_leave_days')
                                            ->label('Jours de congé annuel')
                                            ->numeric()
                                            ->default(24)
                                            ->minValue(0)
                                            ->suffix('jours'),
                                    ])
                                    ->columns(2),

                                Section::make('Renouvellement')
                                    ->schema([
                                        Select::make('renewed_by_contract_id')
                                            ->label('Renouvelé depuis le contrat')
                                            ->options(function ($get): array {
                                                $employeeId = $get('employee_id');

                                                if (! $employeeId) {
                                                    return [];
                                                }

                                                return Contract::where('employee_id', $employeeId)
                                                    ->whereNotNull('contract_number')
                                                    ->pluck('contract_number', 'id')
                                                    ->toArray();
                                            })
                                            ->searchable()
                                            ->placeholder('Nouveau contrat')
                                            ->nullable(),
                                    ]),
                            ]),

                        // ─── Onglet Résiliation ───────────────────────────────
                        Tabs\Tab::make('Résiliation')
                            ->icon('heroicon-o-x-circle')
                            ->visible(fn ($get): bool => in_array(
                                $get('status'),
                                [ContractStatus::Expired->value, ContractStatus::Cancelled->value]
                            ))
                            ->schema([
                                Section::make('Informations de résiliation')
                                    ->schema([
                                        DatePicker::make('termination_date')
                                            ->label('Date de résiliation')
                                            ->native(false)
                                            ->displayFormat('d/m/Y'),

                                        Textarea::make('termination_reason')
                                            ->label('Motif de résiliation')
                                            ->rows(3)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                            ]),

                        // ─── Onglet Notes ─────────────────────────────────────
                        Tabs\Tab::make('Notes')
                            ->icon('heroicon-o-pencil-square')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        Textarea::make('description')
                                            ->label('Description')
                                            ->rows(3)
                                            ->columnSpanFull(),

                                        Textarea::make('notes')
                                            ->label('Notes')
                                            ->rows(4)
                                            ->placeholder('Notes ou clauses particulières du contrat…')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                    ])
                    ->columnSpanFull(),
            ]);
    }
}
