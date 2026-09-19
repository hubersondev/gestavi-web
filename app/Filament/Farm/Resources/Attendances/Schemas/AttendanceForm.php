<?php

namespace App\Filament\Farm\Resources\Attendances\Schemas;

use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class AttendanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Pointage')
                    ->tabs([

                        // ─── Onglet Informations ──────────────────────────────
                        Tabs\Tab::make('Informations')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Section::make('Employé & Date')
                                    ->schema([
                                        Select::make('employee_id')
                                            ->label('Employé')
                                            ->options(function (): array {
                                                $tenant = Filament::getTenant();

                                                return Employee::query()
                                                    ->where('organisation_id', $tenant?->getKey())
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
                                            ->searchable(),

                                        DatePicker::make('attendance_date')
                                            ->label('Date')
                                            ->required()
                                            ->default(now())
                                            ->native(false)
                                            ->displayFormat('d/m/Y'),

                                        Select::make('status')
                                            ->label('Statut')
                                            ->options(AttendanceStatus::class)
                                            ->default(AttendanceStatus::Present)
                                            ->required()
                                            ->native(false)
                                            ->live(),
                                    ])
                                    ->columns(3),

                                Section::make('Horaires')
                                    ->schema([
                                        TimePicker::make('check_in')
                                            ->label("Heure d'arrivée")
                                            ->seconds(false)
                                            ->native(false),

                                        TimePicker::make('check_out')
                                            ->label('Heure de départ')
                                            ->seconds(false)
                                            ->native(false),
                                    ])
                                    ->columns(2)
                                    ->visible(fn ($get): bool => in_array(
                                        $get('status'),
                                        [AttendanceStatus::Present->value, AttendanceStatus::Late->value]
                                    )),

                                Section::make('Détails')
                                    ->schema([
                                        TextInput::make('late_minutes')
                                            ->label('Minutes de retard')
                                            ->numeric()
                                            ->nullable()
                                            ->suffix('min')
                                            ->visible(fn ($get): bool => $get('status') === AttendanceStatus::Late->value),

                                        TextInput::make('overtime_hours')
                                            ->label('Heures supplémentaires')
                                            ->numeric()
                                            ->nullable()
                                            ->step(0.5)
                                            ->suffix('h'),
                                    ])
                                    ->columns(2)
                                    ->visible(fn ($get): bool => in_array(
                                        $get('status'),
                                        [AttendanceStatus::Present->value, AttendanceStatus::Late->value]
                                    )),
                            ]),

                        // ─── Onglet Notes ─────────────────────────────────────
                        Tabs\Tab::make('Notes')
                            ->icon('heroicon-o-pencil-square')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        Select::make('recorded_by')
                                            ->label('Enregistré par')
                                            ->options(function (): array {
                                                $tenant = Filament::getTenant();

                                                if (! $tenant) {
                                                    return [];
                                                }

                                                return User::query()
                                                    ->inOrganisation($tenant->getKey())
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id')
                                                    ->toArray();
                                            })
                                            ->default(fn (): ?int => Filament::auth()->id())
                                            ->searchable()
                                            ->nullable(),

                                        Textarea::make('notes')
                                            ->label('Notes')
                                            ->rows(4)
                                            ->placeholder('Observations, justifications…')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                    ])
                    ->columnSpanFull(),
            ]);
    }
}
