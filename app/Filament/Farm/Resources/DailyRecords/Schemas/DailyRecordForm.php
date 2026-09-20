<?php

namespace App\Filament\Farm\Resources\DailyRecords\Schemas;

use App\Models\DailyRecord;
use App\Models\Farm;
use App\Models\Flock;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
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
use Illuminate\Validation\Rule;

class DailyRecordForm
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
                                Section::make('Contexte')
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
                                            // Garde-fou serveur : la valeur soumise doit appartenir à l'organisation courante
                                            ->rules(fn (): array => [Rule::exists('flocks', 'id')->where('organisation_id', Filament::getTenant()?->getKey())])
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
                                                    ->active()
                                                    ->orderByDesc('start_date')
                                                    ->get()
                                                    ->mapWithKeys(fn (Flock $f) => [$f->id => $f->code.($f->name ? ' — '.$f->name : '')])
                                                    ->toArray();
                                            })
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                                self::recalculateAgeDays($get, $set, $state, $get('record_date'));
                                            })
                                            ->columnSpanFull(),

                                        DatePicker::make('record_date')
                                            ->label('Date du suivi')
                                            ->required()
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->default(now())
                                            ->maxDate(now())
                                            ->rule(fn (Get $get, ?DailyRecord $record) => Rule::unique('daily_records', 'record_date')
                                                ->where('flock_id', $get('flock_id'))
                                                ->ignore($record?->getKey()))
                                            ->validationMessages([
                                                'unique' => 'Un relevé existe déjà pour cette bande à cette date.',
                                            ])
                                            ->live()
                                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                                self::recalculateAgeDays($get, $set, $get('flock_id'), $state);
                                            }),

                                        TextInput::make('age_days')
                                            ->label('Âge (jours)')
                                            ->required()
                                            ->numeric()
                                            ->minValue(0)
                                            ->suffix('jours')
                                            ->helperText('Calculé automatiquement depuis la mise en place'),

                                    ]),
                            ]),

                        Tabs\Tab::make('Mortalité')
                            ->icon('heroicon-o-exclamation-triangle')
                            ->schema([
                                Section::make('Pertes')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('mortality')
                                            ->label('Mortalité')
                                            ->required()
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->maxValue(function (Get $get, ?DailyRecord $record): ?int {
                                                $flock = Flock::query()->find($get('flock_id'));

                                                if (! $flock) {
                                                    return null;
                                                }

                                                // En édition, la mortalité déjà comptée reste disponible
                                                return (int) $flock->quantity_current + (int) ($record?->mortality ?? 0);
                                            })
                                            ->helperText('Ne peut pas dépasser l\'effectif présent de la bande')
                                            ->suffix('sujets'),

                                        TextInput::make('culls')
                                            ->label('Réformes / tri')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->maxValue(function (Get $get, ?DailyRecord $record): ?int {
                                                $flock = Flock::query()->find($get('flock_id'));

                                                if (! $flock) {
                                                    return null;
                                                }

                                                return (int) $flock->quantity_current + (int) ($record?->culls ?? 0);
                                            })
                                            ->helperText('Sujets sortis vivants du lot (tri, réforme), distincts de la mortalité')
                                            ->suffix('sujets'),

                                        Textarea::make('mortality_cause')
                                            ->label('Cause de mortalité')
                                            ->rows(4)
                                            ->placeholder('Maladie, chaleur, prédateur, accident...')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Production')
                            ->icon('heroicon-o-archive-box')
                            ->visible(function (Get $get) {
                                $flockId = $get('flock_id');
                                if (! $flockId) {
                                    return false;
                                }

                                return Flock::with('chickenType')
                                    ->find($flockId)
                                    ?->chickenType
                                    ?->name === 'Pondeuse';
                            })
                            ->schema([
                                Section::make("Production d'œufs")
                                    ->columns(3)
                                    ->schema([
                                        TextInput::make('eggs_collected')
                                            ->label('Œufs collectés')
                                            ->required()
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->suffix('œufs'),

                                        TextInput::make('eggs_broken')
                                            ->label('Œufs cassés')
                                            ->required()
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->suffix('œufs'),

                                        TextInput::make('eggs_sold')
                                            ->label('Œufs vendus')
                                            ->required()
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->suffix('œufs'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Consommation')
                            ->icon('heroicon-o-beaker')
                            ->schema([
                                Section::make('Aliment & Eau')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('feed_consumed_kg')
                                            ->label('Aliment consommé')
                                            ->required()
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->suffix('kg'),

                                        TextInput::make('water_consumed_l')
                                            ->label('Eau consommée')
                                            ->required()
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->suffix('L'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Pesée')
                            ->icon('heroicon-o-scale')
                            ->schema([
                                Section::make('Poids moyen')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('average_weight_g')
                                            ->label('Poids moyen')
                                            ->numeric()
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->suffix('g')
                                            ->placeholder('Optionnel'),

                                        TextInput::make('birds_weighed')
                                            ->label('Nombre de sujets pesés')
                                            ->numeric()
                                            ->minValue(0)
                                            ->placeholder('Optionnel'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Santé')
                            ->icon('heroicon-o-heart')
                            ->schema([
                                Section::make('Observations sanitaires')
                                    ->schema([
                                        Textarea::make('health_observations')
                                            ->label('Observations de santé')
                                            ->rows(4)
                                            ->placeholder('Symptômes observés, comportement anormal...')
                                            ->columnSpanFull(),

                                        Textarea::make('treatments')
                                            ->label('Traitements appliqués')
                                            ->rows(4)
                                            ->placeholder('Médicaments, vaccins, traitements préventifs...')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Environnement')
                            ->icon('heroicon-o-sun')
                            ->schema([
                                Section::make('Conditions climatiques')
                                    ->columns(3)
                                    ->schema([
                                        TextInput::make('temperature_min')
                                            ->label('Température min')
                                            ->numeric()
                                            ->step(0.1)
                                            ->suffix('°C'),

                                        TextInput::make('temperature_max')
                                            ->label('Température max')
                                            ->numeric()
                                            ->step(0.1)
                                            ->suffix('°C'),

                                        TextInput::make('humidity_percent')
                                            ->label('Humidité')
                                            ->numeric()
                                            ->step(0.1)
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->suffix('%'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Notes')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make('Observations générales')
                                    ->schema([
                                        Textarea::make('notes')
                                            ->label('Notes')
                                            ->rows(6)
                                            ->placeholder('Observations, remarques particulières...')
                                            ->columnSpanFull(),

                                        Textarea::make('correction_reason')
                                            ->label('Motif de la correction')
                                            ->helperText('Obligatoire pour toute modification d\'un relevé déjà enregistré ; conservé dans la piste d\'audit.')
                                            ->rows(3)
                                            ->required()
                                            ->visibleOn('edit')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Recalcule age_days à partir de la bande sélectionnée et de la date du suivi.
     */
    private static function recalculateAgeDays(Get $get, Set $set, mixed $flockId, mixed $recordDate): void
    {
        if (! $flockId || ! $recordDate) {
            return;
        }

        $flock = Flock::find($flockId);
        if (! $flock || ! $flock->start_date) {
            return;
        }

        $date = \Carbon\Carbon::parse($recordDate);
        $ageDays = (int) $flock->start_date->diffInDays($date) + ($flock->age_at_arrival ?? 0);
        $set('age_days', max(0, $ageDays));
    }
}
