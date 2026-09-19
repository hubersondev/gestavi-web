<?php

namespace App\Filament\Farm\Resources\Leaves\Schemas;

use App\Enums\EmployeeStatus;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Employee;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class LeaveForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Congé')
                    ->tabs([

                        // ─── Onglet Demande ───────────────────────────────────
                        Tabs\Tab::make('Demande')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make('Employé')
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
                                            ->live(),
                                    ]),

                                Section::make('Type & Statut')
                                    ->schema([
                                        Select::make('type')
                                            ->label('Type de congé')
                                            ->options(LeaveType::class)
                                            ->required()
                                            ->native(false)
                                            ->live(),

                                        Select::make('status')
                                            ->label('Statut')
                                            ->options(LeaveStatus::class)
                                            ->default(LeaveStatus::Pending)
                                            ->required()
                                            ->native(false),
                                    ])
                                    ->columns(2),

                                Section::make('Période')
                                    ->schema([
                                        DatePicker::make('start_date')
                                            ->label('Date de début')
                                            ->required()
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->live()
                                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                                                $start = $state;
                                                $end = $get('end_date');

                                                if ($start && $end) {
                                                    $days = (int) \Carbon\Carbon::parse($start)->diffInDays(\Carbon\Carbon::parse($end)) + 1;
                                                    $set('days_count', max(1, $days));
                                                }
                                            }),

                                        DatePicker::make('end_date')
                                            ->label('Date de fin')
                                            ->required()
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->live()
                                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                                                $start = $get('start_date');
                                                $end = $state;

                                                if ($start && $end) {
                                                    $days = (int) \Carbon\Carbon::parse($start)->diffInDays(\Carbon\Carbon::parse($end)) + 1;
                                                    $set('days_count', max(1, $days));
                                                }
                                            }),

                                        TextInput::make('days_count')
                                            ->label('Nombre de jours')
                                            ->numeric()
                                            ->default(1)
                                            ->disabled()
                                            ->dehydrated(),
                                    ])
                                    ->columns(3),

                                Section::make('Motif')
                                    ->schema([
                                        Textarea::make('reason')
                                            ->label('Motif de la demande')
                                            ->rows(3)
                                            ->required()
                                            ->placeholder('Indiquez le motif de la demande de congé…')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        // ─── Onglet Justificatif ──────────────────────────────
                        Tabs\Tab::make('Justificatif')
                            ->icon('heroicon-o-paper-clip')
                            ->schema([
                                Section::make('Pièce jointe')
                                    ->description('Certificat médical, convocation, etc.')
                                    ->schema([
                                        FileUpload::make('attachment')
                                            ->label('Document')
                                            ->disk('local')
                                            ->visibility('private')
                                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                                            ->directory('leaves/attachments')
                                            ->maxSize(5120)
                                            ->downloadable()
                                            ->openable()
                                            ->columnSpanFull(),
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
                                            ->rows(4)
                                            ->placeholder('Notes complémentaires…')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                    ])
                    ->columnSpanFull(),
            ]);
    }
}
