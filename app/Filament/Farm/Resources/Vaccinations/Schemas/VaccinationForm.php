<?php

namespace App\Filament\Farm\Resources\Vaccinations\Schemas;

use App\Enums\VaccinationStatus;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\HealthProgram;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class VaccinationForm
{
    /**
     * Options partagées pour le type de traitement (aligné sur HealthProgram).
     */
    public static function treatmentTypeOptions(): array
    {
        return [
            'vaccin' => 'Vaccin',
            'vermifuge' => 'Vermifuge',
            'anticoccidien' => 'Anticoccidien',
            'vitamine' => 'Vitamine / Complément',
            'antibiotique' => 'Antibiotique',
            'antiparasitaire' => 'Antiparasitaire',
            'desinfectant' => 'Désinfectant',
            'autre' => 'Autre',
        ];
    }

    /**
     * Options partagées pour la voie d'administration (aligné sur HealthProgram).
     */
    public static function administrationRouteOptions(): array
    {
        return [
            'eau' => "Dans l'eau de boisson",
            'injection' => 'Par injection',
            'spray' => 'Par nébulisation',
            'oculaire' => 'Goutte oculaire',
            'aliment' => "Dans l'aliment",
            'autre' => 'Autre',
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Informations')
                    ->tabs([
                        Tabs\Tab::make('Informations générales')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Section::make('Bande & Programme')
                                    ->columns(2)
                                    ->schema([
                                        Select::make('farm_filter')
                                            ->label('Ferme')
                                            ->options(function () {
                                                $tenant = Filament::getTenant();
                                                $user = Filament::auth()->user();
                                                $membership = $user?->membershipIn($tenant);

                                                return Farm::query()
                                                    ->where('organisation_id', $tenant?->getKey())
                                                    ->when($membership, fn ($q) => $q->accessibleBy($membership))
                                                    ->where('is_active', true)
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id');
                                            })
                                            ->default(function () {
                                                $membership = Filament::auth()->user()?->membershipIn(Filament::getTenant());

                                                return $membership
                                                    ? app(CurrentFarm::class)->get($membership)?->id
                                                    : null;
                                            })
                                            ->hidden(function () {
                                                $membership = Filament::auth()->user()?->membershipIn(Filament::getTenant());

                                                return $membership
                                                    && app(CurrentFarm::class)->get($membership) !== null;
                                            })
                                            ->live()
                                            ->afterStateUpdated(fn (Set $set) => $set('flock_id', null))
                                            ->searchable()
                                            ->preload()
                                            ->dehydrated(false)
                                            ->columnSpanFull(),

                                        Select::make('flock_id')
                                            ->label('Bande')
                                            ->options(function (Get $get) {
                                                $tenant = Filament::getTenant();
                                                $user = Filament::auth()->user();
                                                $membership = $user?->membershipIn($tenant);
                                                $currentFarm = $membership
                                                    ? app(CurrentFarm::class)->get($membership)
                                                    : null;
                                                $farmFilterId = $currentFarm?->id ?? $get('farm_filter');

                                                return Flock::query()
                                                    ->whereHas('building.farm', function (Builder $q) use ($tenant, $membership, $farmFilterId) {
                                                        $q->where('organisation_id', $tenant?->getKey());

                                                        if ($membership) {
                                                            $q->accessibleBy($membership);
                                                        }

                                                        if ($farmFilterId) {
                                                            $q->where('id', $farmFilterId);
                                                        }
                                                    })
                                                    ->whereIn('status', ['planned', 'active'])
                                                    ->orderByDesc('start_date')
                                                    ->get()
                                                    ->mapWithKeys(fn (Flock $f) => [$f->id => $f->code.($f->name ? ' — '.$f->name : '')])
                                                    ->toArray();
                                            })
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(function (Set $set) {
                                                $set('health_program_id', null);
                                            })
                                            ->columnSpanFull(),

                                        Select::make('health_program_id')
                                            ->label('Programme sanitaire (optionnel)')
                                            ->options(function (Get $get) {
                                                $flockId = $get('flock_id');
                                                if (! $flockId) {
                                                    return [];
                                                }

                                                $flock = Flock::with('building.farm')->find($flockId);
                                                if (! $flock) {
                                                    return [];
                                                }

                                                return HealthProgram::getProgramForFlock($flock)
                                                    ->mapWithKeys(fn (HealthProgram $hp) => [
                                                        $hp->id => "J{$hp->scheduled_day} — {$hp->treatment_name}",
                                                    ])
                                                    ->toArray();
                                            })
                                            ->nullable()
                                            ->searchable()
                                            ->live()
                                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                                if (! $state) {
                                                    return;
                                                }

                                                $program = HealthProgram::find($state);
                                                if (! $program) {
                                                    return;
                                                }

                                                $set('treatment_name', $program->treatment_name);
                                                $set('treatment_type', $program->treatment_type);
                                                $set('administration_route', $program->administration_route);
                                                $set('dosage', $program->dosage);

                                                // Calculer la date prévue depuis l'âge du programme
                                                $flockId = $get('flock_id');
                                                if ($flockId) {
                                                    $flock = Flock::find($flockId);
                                                    if ($flock?->start_date) {
                                                        $daysOffset = $program->scheduled_day - ($flock->age_at_arrival ?? 0);
                                                        $scheduledDate = $flock->start_date->copy()->addDays(max(0, $daysOffset));
                                                        $set('scheduled_date', $scheduledDate->toDateString());
                                                        $set('age_at_treatment', $program->scheduled_day);
                                                    }
                                                }
                                            })
                                            ->placeholder('Sélectionner depuis un programme (optionnel)')
                                            ->helperText('Pré-remplit les champs depuis le programme sélectionné')
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('Traitement')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('treatment_name')
                                            ->label('Nom du traitement')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('Ex: Gumboro, Newcastle, Vitamine C...'),

                                        Select::make('treatment_type')
                                            ->label('Type de traitement')
                                            ->required()
                                            ->options(self::treatmentTypeOptions())
                                            ->native(false)
                                            ->searchable(),

                                        Select::make('administration_route')
                                            ->label("Voie d'administration")
                                            ->options(self::administrationRouteOptions())
                                            ->native(false)
                                            ->nullable(),

                                        TextInput::make('dosage')
                                            ->label('Dosage')
                                            ->maxLength(100)
                                            ->placeholder('Ex: 0,5 ml/L, 1 dose/sujet...'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Planification')
                            ->icon('heroicon-o-calendar')
                            ->schema([
                                Section::make('Dates')
                                    ->columns(3)
                                    ->schema([
                                        DatePicker::make('scheduled_date')
                                            ->label('Date prévue')
                                            ->required()
                                            ->native(false)
                                            ->displayFormat('d/m/Y'),

                                        DatePicker::make('administered_date')
                                            ->label('Date réalisée')
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->placeholder('Non encore effectuée'),

                                        TextInput::make('age_at_treatment')
                                            ->label('Âge prévu')
                                            ->numeric()
                                            ->minValue(0)
                                            ->suffix('jours')
                                            ->placeholder('Optionnel'),
                                    ]),

                                Section::make('Statut')
                                    ->schema([
                                        Select::make('status')
                                            ->label('Statut')
                                            ->options(collect(VaccinationStatus::cases())
                                                ->mapWithKeys(fn (VaccinationStatus $s) => [$s->value => $s->label()])
                                                ->toArray())
                                            ->default(VaccinationStatus::Pending->value)
                                            ->required()
                                            ->native(false),
                                    ]),
                            ]),

                        Tabs\Tab::make('Administration')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Section::make('Réalisation')
                                    ->columns(2)
                                    ->schema([
                                        Select::make('administered_by')
                                            ->label('Administrateur')
                                            ->options(function () {
                                                $tenant = Filament::getTenant();

                                                return $tenant
                                                    ->activeMembers()
                                                    ->orderBy('name')
                                                    ->pluck('name', 'users.id');
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->nullable(),

                                        TextInput::make('birds_treated')
                                            ->label('Sujets traités')
                                            ->numeric()
                                            ->minValue(0)
                                            ->placeholder('Optionnel'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Produit')
                            ->icon('heroicon-o-beaker')
                            ->schema([
                                Section::make('Informations produit')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('batch_number')
                                            ->label('Numéro de lot')
                                            ->maxLength(100)
                                            ->placeholder('Ex: LOT-2026-001'),

                                        DatePicker::make('expiry_date')
                                            ->label('Date d\'expiration')
                                            ->native(false)
                                            ->displayFormat('d/m/Y'),

                                        TextInput::make('cost')
                                            ->label('Coût total')
                                            ->numeric()
                                            ->step(0.01)
                                            ->suffix('FCFA'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Notes')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make('Observations')
                                    ->schema([
                                        Textarea::make('observations')
                                            ->label('Observations')
                                            ->rows(4)
                                            ->placeholder('Résultat, réactions observées...')
                                            ->columnSpanFull(),

                                        Textarea::make('notes')
                                            ->label('Notes internes')
                                            ->rows(4)
                                            ->placeholder('Informations complémentaires...')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
