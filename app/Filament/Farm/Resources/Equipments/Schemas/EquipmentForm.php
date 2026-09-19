<?php

namespace App\Filament\Farm\Resources\Equipments\Schemas;

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

class EquipmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Informations')
                    ->tabs([
                        Tabs\Tab::make('Identification')
                            ->icon('heroicon-o-identification')
                            ->schema([
                                Section::make('Informations générales')
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Nom de l\'équipement')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('Ex: Mangeoire automatique 50L'),

                                        TextInput::make('inventory_number')
                                            ->label('N° d\'inventaire')
                                            ->placeholder('Généré automatiquement')
                                            ->maxLength(50)
                                            ->disabled()
                                            ->dehydrated(),

                                        Select::make('category')
                                            ->label('Catégorie')
                                            ->required()
                                            ->options([
                                                'alimentation' => 'Alimentation',
                                                'abreuvement' => 'Abreuvement',
                                                'ventilation' => 'Ventilation',
                                                'chauffage' => 'Chauffage',
                                                'eclairage' => 'Éclairage',
                                                'pesage' => 'Pesage',
                                                'transport' => 'Transport',
                                                'nettoyage' => 'Nettoyage',
                                                'stockage' => 'Stockage',
                                                'abattage' => 'Abattage',
                                                'autre' => 'Autre',
                                            ])
                                            ->native(false)
                                            ->searchable(),
                                    ])
                                    ->columns(2),

                                Section::make('Caractéristiques')
                                    ->schema([
                                        TextInput::make('brand')
                                            ->label('Marque')
                                            ->maxLength(100)
                                            ->placeholder('Ex: Big Dutchman'),

                                        TextInput::make('model')
                                            ->label('Modèle')
                                            ->maxLength(100)
                                            ->placeholder('Ex: F-100'),

                                        TextInput::make('serial_number')
                                            ->label('N° de série')
                                            ->maxLength(100)
                                            ->placeholder('Numéro de série fabricant'),

                                        TextInput::make('capacity')
                                            ->label('Capacité')
                                            ->numeric()
                                            ->placeholder('Ex: 50'),

                                        TextInput::make('capacity_unit')
                                            ->label('Unité')
                                            ->maxLength(20)
                                            ->placeholder('Ex: litres, kg, poules'),
                                    ])
                                    ->columns(3),
                            ]),

                        Tabs\Tab::make('Emplacement')
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                Section::make('Localisation')
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
                                                        return $farmFilterId
                                                            ? [$b->id => $b->name]
                                                            : [$b->id => $b->farm->name.' — '.$b->name];
                                                    })
                                                    ->toArray();
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->placeholder('En stock (non assigné à un bâtiment)')
                                            ->helperText('Laisser vide si l\'équipement est en stock')
                                            ->columnSpan(2),

                                        Select::make('status')
                                            ->label('Statut')
                                            ->required()
                                            ->options([
                                                'in_service' => 'En service',
                                                'in_stock' => 'En stock',
                                                'in_maintenance' => 'En maintenance',
                                                'out_of_service' => 'Hors service',
                                                'disposed' => 'Réformé',
                                            ])
                                            ->default('in_stock')
                                            ->native(false),

                                        Select::make('condition')
                                            ->label('État')
                                            ->options([
                                                'new' => 'Neuf',
                                                'good' => 'Bon état',
                                                'fair' => 'État moyen',
                                                'poor' => 'Mauvais état',
                                                'broken' => 'Cassé',
                                            ])
                                            ->default('new')
                                            ->native(false),
                                    ])
                                    ->columns(4),
                            ]),

                        Tabs\Tab::make('Achat & Garantie')
                            ->icon('heroicon-o-shopping-cart')
                            ->schema([
                                Section::make('Achat')
                                    ->schema([
                                        Select::make('supplier_id')
                                            ->label('Fournisseur')
                                            ->options(function () {
                                                $tenant = Filament::getTenant();
                                                $user = Filament::auth()->user();
                                                $membership = $user?->membershipIn($tenant);

                                                return Supplier::query()
                                                    ->active()
                                                    ->where(function ($q) use ($tenant, $membership) {
                                                        // Fournisseurs liés à une ferme accessible
                                                        $q->whereHas('farm', function ($fq) use ($tenant, $membership) {
                                                            $fq->where('organisation_id', $tenant?->getKey());
                                                            if ($membership) {
                                                                $fq->accessibleBy($membership);
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
                                            ->preload(),

                                        DatePicker::make('purchase_date')
                                            ->label('Date d\'achat')
                                            ->native(false)
                                            ->displayFormat('d/m/Y'),

                                        TextInput::make('purchase_price')
                                            ->label('Prix d\'achat')
                                            ->numeric()
                                            ->suffix('FCFA')
                                            ->placeholder('Prix en FCFA'),

                                        TextInput::make('purchase_reference')
                                            ->label('Réf. achat')
                                            ->maxLength(100)
                                            ->placeholder('N° bon de commande, facture...'),
                                    ])
                                    ->columns(2),

                                Section::make('Garantie & Amortissement')
                                    ->schema([
                                        DatePicker::make('warranty_expiry_date')
                                            ->label('Fin de garantie')
                                            ->native(false)
                                            ->displayFormat('d/m/Y'),

                                        TextInput::make('lifespan_years')
                                            ->label('Durée de vie (années)')
                                            ->numeric()
                                            ->minValue(1)
                                            ->placeholder('Ex: 5')
                                            ->helperText('Pour le calcul de l\'amortissement'),

                                        TextInput::make('current_value')
                                            ->label('Valeur actuelle')
                                            ->numeric()
                                            ->suffix('FCFA')
                                            ->disabled()
                                            ->dehydrated()
                                            ->helperText('Calculée automatiquement'),
                                    ])
                                    ->columns(3),
                            ]),

                        Tabs\Tab::make('Maintenance')
                            ->icon('heroicon-o-wrench')
                            ->hiddenOn('create')
                            ->schema([
                                Section::make('Suivi maintenance')
                                    ->schema([
                                        DatePicker::make('last_maintenance_date')
                                            ->label('Dernière maintenance')
                                            ->native(false)
                                            ->displayFormat('d/m/Y'),

                                        DatePicker::make('next_maintenance_date')
                                            ->label('Prochaine maintenance')
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->helperText('Date planifiée pour la prochaine maintenance'),

                                        TextInput::make('maintenance_interval_days')
                                            ->label('Intervalle (jours)')
                                            ->numeric()
                                            ->minValue(1)
                                            ->placeholder('Ex: 30')
                                            ->helperText('Fréquence de maintenance en jours'),
                                    ])
                                    ->columns(3),
                            ]),

                        Tabs\Tab::make('Notes')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make('Description et notes')
                                    ->schema([
                                        Textarea::make('description')
                                            ->label('Description')
                                            ->rows(3)
                                            ->placeholder('Description détaillée de l\'équipement...'),

                                        Textarea::make('notes')
                                            ->label('Notes')
                                            ->rows(3)
                                            ->placeholder('Notes internes...'),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
