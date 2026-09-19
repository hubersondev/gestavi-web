<?php

namespace App\Filament\Farm\Resources\HealthIncidents\Schemas;

use App\Enums\IncidentCategory;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Models\Farm;
use App\Models\Flock;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class HealthIncidentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Informations')
                    ->tabs([
                        Tabs\Tab::make('Informations générales')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Section::make('Bande concernée')
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
                                                    ->where('status', 'active')
                                                    ->orderByDesc('start_date')
                                                    ->get()
                                                    ->mapWithKeys(fn (Flock $f) => [$f->id => $f->code.($f->name ? ' — '.$f->name : '')])
                                                    ->toArray();
                                            })
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('Incident')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('title')
                                            ->label("Titre de l'incident")
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('Ex: Mortalité élevée bâtiment A, Toux persistante...')
                                            ->columnSpanFull(),

                                        DatePicker::make('incident_date')
                                            ->label("Date de l'incident")
                                            ->required()
                                            ->native(false)
                                            ->displayFormat('d/m/Y'),

                                        DatePicker::make('detected_date')
                                            ->label('Date de détection')
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->default(now()),

                                        Select::make('severity')
                                            ->label('Gravité')
                                            ->required()
                                            ->options(collect(IncidentSeverity::cases())
                                                ->mapWithKeys(fn (IncidentSeverity $s) => [$s->value => $s->label()])
                                                ->toArray())
                                            ->native(false),

                                        Select::make('category')
                                            ->label('Catégorie')
                                            ->required()
                                            ->options(collect(IncidentCategory::cases())
                                                ->mapWithKeys(fn (IncidentCategory $c) => [$c->value => $c->label()])
                                                ->toArray())
                                            ->native(false)
                                            ->searchable(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Symptômes & Diagnostic')
                            ->icon('heroicon-o-magnifying-glass')
                            ->schema([
                                Section::make('Observations')
                                    ->schema([
                                        Textarea::make('symptoms')
                                            ->label('Symptômes observés')
                                            ->required()
                                            ->rows(4)
                                            ->placeholder('Décrire les symptômes observés...')
                                            ->columnSpanFull(),

                                        TextInput::make('suspected_disease')
                                            ->label('Maladie suspectée')
                                            ->maxLength(255)
                                            ->placeholder('Ex: Gumboro, Newcastle, Coccidiose...'),

                                        TextInput::make('confirmed_disease')
                                            ->label('Maladie confirmée')
                                            ->maxLength(255)
                                            ->placeholder('Après diagnostic/analyse'),
                                    ])
                                    ->columns(2),

                                Section::make('Impact sur le troupeau')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('birds_affected')
                                            ->label('Sujets affectés')
                                            ->required()
                                            ->numeric()
                                            ->minValue(0)
                                            ->suffix('sujets'),

                                        TextInput::make('birds_dead')
                                            ->label('Sujets morts')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->suffix('sujets'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Traitement')
                            ->icon('heroicon-o-beaker')
                            ->schema([
                                Section::make('Traitement appliqué')
                                    ->schema([
                                        Textarea::make('treatment_applied')
                                            ->label('Description du traitement')
                                            ->rows(4)
                                            ->placeholder('Médicaments utilisés, protocole suivi...')
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('Dates & Coût')
                                    ->columns(3)
                                    ->schema([
                                        DatePicker::make('treatment_start_date')
                                            ->label('Début du traitement')
                                            ->native(false)
                                            ->displayFormat('d/m/Y'),

                                        DatePicker::make('treatment_end_date')
                                            ->label('Fin du traitement')
                                            ->native(false)
                                            ->displayFormat('d/m/Y'),

                                        TextInput::make('treatment_cost')
                                            ->label('Coût du traitement')
                                            ->numeric()
                                            ->step(0.01)
                                            ->suffix('FCFA'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Quarantaine')
                            ->icon('heroicon-o-shield-exclamation')
                            ->schema([
                                Section::make('Mesures de quarantaine')
                                    ->columns(2)
                                    ->schema([
                                        Checkbox::make('quarantine_applied')
                                            ->label('Quarantaine appliquée')
                                            ->live()
                                            ->columnSpanFull(),

                                        DatePicker::make('quarantine_start_date')
                                            ->label('Début de quarantaine')
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->visible(fn (Get $get) => $get('quarantine_applied')),

                                        DatePicker::make('quarantine_end_date')
                                            ->label('Fin de quarantaine')
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->visible(fn (Get $get) => $get('quarantine_applied')),
                                    ]),
                            ]),

                        Tabs\Tab::make('Résolution & Suivi')
                            ->icon('heroicon-o-check-circle')
                            ->schema([
                                Section::make('Statut')
                                    ->columns(2)
                                    ->schema([
                                        Select::make('status')
                                            ->label('Statut')
                                            ->required()
                                            ->options(collect(IncidentStatus::cases())
                                                ->mapWithKeys(fn (IncidentStatus $s) => [$s->value => $s->label()])
                                                ->toArray())
                                            ->default(IncidentStatus::Detected->value)
                                            ->native(false),

                                        DatePicker::make('resolution_date')
                                            ->label('Date de résolution')
                                            ->native(false)
                                            ->displayFormat('d/m/Y'),

                                        Textarea::make('resolution_notes')
                                            ->label('Notes de résolution')
                                            ->rows(3)
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('Amélioration continue')
                                    ->schema([
                                        Textarea::make('lessons_learned')
                                            ->label('Leçons apprises')
                                            ->rows(3)
                                            ->placeholder("Ce que cet incident nous a appris...")
                                            ->columnSpanFull(),

                                        Textarea::make('preventive_measures')
                                            ->label('Mesures préventives')
                                            ->rows(3)
                                            ->placeholder('Mesures à mettre en place pour éviter une récidive...')
                                            ->columnSpanFull(),

                                        Textarea::make('notes')
                                            ->label('Notes internes')
                                            ->rows(3)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
