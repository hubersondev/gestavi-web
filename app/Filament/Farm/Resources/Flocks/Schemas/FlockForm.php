<?php

namespace App\Filament\Farm\Resources\Flocks\Schemas;

use App\Enums\FlockStatus;
use App\Models\Building;
use App\Models\Farm;
use App\Models\Supplier;
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
use Illuminate\Validation\Rule;

class FlockForm
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
                                Section::make('Identification')
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
                                            ->afterStateUpdated(fn (Set $set) => $set('building_id', null))
                                            ->searchable()
                                            ->preload()
                                            ->dehydrated(false)
                                            ->columnSpan(2),

                                        Select::make('building_id')
                                            ->label('Bâtiment')
                                            ->options(function (Get $get) {
                                                $tenant = Filament::getTenant();
                                                $user = Filament::auth()->user();
                                                $membership = $user?->membershipIn($tenant);

                                                // Priorité : ferme sélectionnée dans le header, sinon dans le select du formulaire
                                                $currentFarm = $membership
                                                    ? app(CurrentFarm::class)->get($membership)
                                                    : null;
                                                $farmFilterId = $currentFarm?->id ?? $get('farm_filter');

                                                return Building::query()
                                                    ->whereHas('farm', function ($q) use ($tenant, $membership, $farmFilterId) {
                                                        $q->where('organisation_id', $tenant?->getKey());

                                                        if ($membership) {
                                                            $q->accessibleBy($membership);
                                                        }

                                                        if ($farmFilterId) {
                                                            $q->where('id', $farmFilterId);
                                                        }
                                                    })
                                                    ->get()
                                                    ->mapWithKeys(function (Building $b) use ($farmFilterId) {
                                                        // Masquer le préfixe "Ferme — " si une ferme est déjà sélectionnée
                                                        return $farmFilterId
                                                            ? [$b->id => $b->name]
                                                            : [$b->id => $b->farm->name.' — '.$b->name];
                                                    })
                                                    ->toArray();
                                            })
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->columnSpan(2),

                                        TextInput::make('code')
                                            ->label('Code')
                                            ->required()
                                            ->maxLength(50)
                                            ->placeholder('LOT-001')
                                            ->helperText('Code unique de la bande par organisation')
                                            ->rules(function ($record) {
                                                $tenant = Filament::getTenant();

                                                return [
                                                    Rule::unique('flocks', 'code')
                                                        ->where(function ($query) use ($tenant) {
                                                            $query->whereExists(function ($sub) use ($tenant) {
                                                                $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                                                                    ->from('buildings')
                                                                    ->join('farms', 'buildings.farm_id', '=', 'farms.id')
                                                                    ->whereColumn('flocks.building_id', 'buildings.id')
                                                                    ->where('farms.organisation_id', $tenant?->getKey());
                                                            });
                                                        })
                                                        ->ignore($record?->id),
                                                ];
                                            }),

                                        TextInput::make('name')
                                            ->label('Nom')
                                            ->maxLength(255)
                                            ->placeholder('Ex: Bande Chair Mars 2026'),

                                        Select::make('chicken_type_id')
                                            ->label('Type de volaille')
                                            ->relationship('chickenType', 'name')
                                            ->searchable()
                                            ->preload(),

                                        TextInput::make('breed')
                                            ->label('Race')
                                            ->maxLength(100)
                                            ->placeholder('Ex: Ross 308, Cobb 500, ISA Brown...'),

                                        Select::make('status')
                                            ->label('Statut')
                                            ->options(FlockStatus::class)
                                            ->default(FlockStatus::Planned)
                                            ->required()
                                            ->native(false),
                                    ])
                                    ->columns(2),
                            ]),

                        Tabs\Tab::make('Effectifs')
                            ->icon('heroicon-o-user-group')
                            ->schema([
                                Section::make('Quantités')
                                    ->schema([
                                        TextInput::make('quantity_started')
                                            ->label('Effectif initial')
                                            ->required()
                                            ->numeric()
                                            ->minValue(1)
                                            ->suffix('sujets')
                                            ->helperText('Nombre de sujets à la mise en place')
                                            ->live(debounce: 500)
                                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                                // Synchroniser quantity_current si vide ou 0
                                                if (! $get('quantity_current') || $get('quantity_current') == 0) {
                                                    $set('quantity_current', $state);
                                                }

                                                // Recalculer le prix total
                                                $pricePerUnit = $get('purchase_price_per_unit');
                                                if ($state && $pricePerUnit) {
                                                    $set('total_purchase_price', round((float) $state * (float) $pricePerUnit, 2));
                                                }
                                            }),

                                        TextInput::make('quantity_current')
                                            ->label('Effectif actuel')
                                            ->numeric()
                                            ->minValue(0)
                                            ->suffix('sujets')
                                            ->helperText('Nombre de sujets vivants actuellement'),

                                        TextInput::make('age_at_arrival')
                                            ->label('Âge à la réception')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->suffix('jours')
                                            ->helperText('Âge des sujets à leur arrivée'),
                                    ])
                                    ->columns(3),
                            ]),

                        Tabs\Tab::make('Dates')
                            ->icon('heroicon-o-calendar')
                            ->schema([
                                Section::make('Période de production')
                                    ->schema([
                                        DatePicker::make('start_date')
                                            ->label('Date de mise en place')
                                            ->required()
                                            ->native(false)
                                            ->displayFormat('d/m/Y'),

                                        DatePicker::make('expected_end_date')
                                            ->label('Date de fin prévue')
                                            ->native(false)
                                            ->displayFormat('d/m/Y'),

                                        DatePicker::make('actual_end_date')
                                            ->label('Date de fin réelle')
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->helperText('Renseignée à la clôture de la bande'),
                                    ])
                                    ->columns(3),
                            ]),

                        Tabs\Tab::make('Approvisionnement')
                            ->icon('heroicon-o-shopping-cart')
                            ->schema([
                                Section::make('Achat des sujets')
                                    ->schema([
                                        Select::make('supplier_id')
                                            ->label('Fournisseur (couvoir)')
                                            ->options(function () {
                                                $tenant = Filament::getTenant();
                                                $user = Filament::auth()->user();
                                                $membership = $user?->membershipIn($tenant);
                                                $currentFarm = $membership
                                                    ? app(CurrentFarm::class)->get($membership)
                                                    : null;

                                                return Supplier::query()
                                                    ->ofType('poussin')
                                                    ->active()
                                                    ->where(function ($q) use ($tenant, $membership, $currentFarm) {
                                                        // Fournisseurs liés à une ferme accessible
                                                        $q->whereHas('farm', function ($fq) use ($tenant, $membership, $currentFarm) {
                                                            $fq->where('organisation_id', $tenant?->getKey());
                                                            if ($membership) {
                                                                $fq->accessibleBy($membership);
                                                            }
                                                            if ($currentFarm) {
                                                                $fq->where('id', $currentFarm->id);
                                                            }
                                                        });
                                                        // Fournisseurs réseau de l'organisation
                                                        $q->orWhere(function ($nq) use ($tenant) {
                                                            $nq->whereNull('farm_id')
                                                                ->where('organisation_id', $tenant?->getKey());
                                                        });
                                                    })
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id');
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->placeholder('Sélectionner un fournisseur'),

                                        TextInput::make('purchase_price_per_unit')
                                            ->label('Prix unitaire')
                                            ->numeric()
                                            ->suffix('FCFA')
                                            ->placeholder('Prix par sujet')
                                            ->live(debounce: 500)
                                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                                $quantity = $get('quantity_started');
                                                if ($state && $quantity) {
                                                    $set('total_purchase_price', round((float) $state * (float) $quantity, 2));
                                                }
                                            }),

                                        TextInput::make('total_purchase_price')
                                            ->label('Prix total')
                                            ->numeric()
                                            ->suffix('FCFA')
                                            ->placeholder('Calculé automatiquement')
                                            ->helperText('Prix unitaire × Effectif initial'),
                                    ])
                                    ->columns(3),
                            ]),

                        Tabs\Tab::make('Notes')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make('Observations')
                                    ->schema([
                                        Textarea::make('notes')
                                            ->label('Notes')
                                            ->rows(4)
                                            ->placeholder('Observations, remarques particulières...'),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
