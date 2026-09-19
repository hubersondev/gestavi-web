<?php

namespace App\Filament\Farm\Resources\FarmLogs\Schemas;

use App\Enums\FarmLogCategory;
use App\Enums\FarmLogPriority;
use App\Models\Building;
use App\Models\Farm;
use App\Models\Flock;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class FarmLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Journal de ferme')
                    ->tabs([
                        Tabs\Tab::make('Informations générales')
                            ->icon('heroicon-o-clipboard-document-list')
                            ->schema([
                                Section::make('Localisation')
                                    ->schema([
                                        Select::make('farm_id')
                                            ->label('Ferme')
                                            ->options(function () {
                                                $tenant = Filament::getTenant();
                                                $user = Filament::auth()->user();
                                                $membership = $user?->membershipIn($tenant);

                                                return Farm::query()
                                                    ->where('organisation_id', $tenant?->getKey())
                                                    ->when($membership, fn (Builder $q) => $q->accessibleBy($membership))
                                                    ->pluck('name', 'id');
                                            })
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->live()
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
                                            ->columnSpan(2),

                                        Select::make('flock_id')
                                            ->label('Bande (optionnel)')
                                            ->options(function (Get $get) {
                                                $farmId = $get('farm_id');

                                                if (! $farmId) {
                                                    $membership = Filament::auth()->user()?->membershipIn(Filament::getTenant());
                                                    $currentFarm = $membership ? app(CurrentFarm::class)->get($membership) : null;
                                                    $farmId = $currentFarm?->id;
                                                }

                                                if (! $farmId) {
                                                    return [];
                                                }

                                                return Flock::query()
                                                    ->whereHas('building', fn (Builder $q) => $q->where('farm_id', $farmId))
                                                    ->orderByDesc('start_date')
                                                    ->get()
                                                    ->mapWithKeys(fn (Flock $f) => [$f->id => $f->code.($f->name ? ' — '.$f->name : '')])
                                                    ->toArray();
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->placeholder('Ferme entière'),

                                        Select::make('building_id')
                                            ->label('Bâtiment (optionnel)')
                                            ->options(function (Get $get) {
                                                $farmId = $get('farm_id');

                                                if (! $farmId) {
                                                    $membership = Filament::auth()->user()?->membershipIn(Filament::getTenant());
                                                    $currentFarm = $membership ? app(CurrentFarm::class)->get($membership) : null;
                                                    $farmId = $currentFarm?->id;
                                                }

                                                if (! $farmId) {
                                                    return [];
                                                }

                                                return Building::query()
                                                    ->where('farm_id', $farmId)
                                                    ->pluck('name', 'id');
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->placeholder('Aucun bâtiment spécifique'),
                                    ])
                                    ->columns(2),

                                Section::make('Entrée')
                                    ->schema([
                                        DatePicker::make('log_date')
                                            ->label('Date')
                                            ->required()
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->default(now()),

                                        TimePicker::make('log_time')
                                            ->label('Heure')
                                            ->seconds(false)
                                            ->native(false),

                                        Select::make('category')
                                            ->label('Catégorie')
                                            ->options(FarmLogCategory::class)
                                            ->required()
                                            ->default(FarmLogCategory::Observation->value)
                                            ->native(false),

                                        Select::make('priority')
                                            ->label('Priorité')
                                            ->options(FarmLogPriority::class)
                                            ->required()
                                            ->default(FarmLogPriority::Normal->value)
                                            ->native(false),

                                        TextInput::make('title')
                                            ->label('Titre')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('Résumé de l\'événement...')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                            ]),

                        Tabs\Tab::make('Description')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        Textarea::make('description')
                                            ->label('Description')
                                            ->rows(5)
                                            ->placeholder('Décrivez en détail ce qui s\'est passé...')
                                            ->columnSpanFull(),

                                        Textarea::make('actions_taken')
                                            ->label('Actions entreprises')
                                            ->rows(4)
                                            ->placeholder('Quelles actions ont été prises ou décisions ont été prises...')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Conditions')
                            ->icon('heroicon-o-sun')
                            ->schema([
                                Section::make('Météo & Environnement')
                                    ->schema([
                                        Select::make('weather')
                                            ->label('Météo')
                                            ->options([
                                                'sunny' => 'Ensoleillé',
                                                'cloudy' => 'Nuageux',
                                                'rainy' => 'Pluvieux',
                                                'windy' => 'Venteux',
                                                'stormy' => 'Orageux',
                                                'hot' => 'Très chaud',
                                                'cold' => 'Froid',
                                                'humid' => 'Très humide',
                                            ])
                                            ->native(false)
                                            ->placeholder('Sélectionner...'),

                                        TextInput::make('temperature')
                                            ->label('Température ambiante')
                                            ->numeric()
                                            ->step(0.1)
                                            ->suffix('°C'),

                                        TextInput::make('humidity')
                                            ->label('Humidité')
                                            ->numeric()
                                            ->step(0.1)
                                            ->suffix('%'),
                                    ])
                                    ->columns(3),
                            ]),

                        Tabs\Tab::make('Suivi')
                            ->icon('heroicon-o-arrow-path')
                            ->schema([
                                Section::make('Suivi requis')
                                    ->schema([
                                        Toggle::make('requires_follow_up')
                                            ->label('Nécessite un suivi')
                                            ->live()
                                            ->inline(false),

                                        DatePicker::make('follow_up_date')
                                            ->label('Date de suivi prévue')
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->visible(fn (Get $get) => $get('requires_follow_up')),
                                    ])
                                    ->columns(2),

                                Section::make()
                                    ->schema([
                                        Textarea::make('notes')
                                            ->label('Notes complémentaires')
                                            ->rows(4)
                                            ->placeholder('Informations supplémentaires...')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
