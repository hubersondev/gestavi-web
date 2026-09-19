<?php

namespace App\Filament\Farm\Resources\Stocks\Schemas;

use App\Models\Farm;
use App\Models\StockCategory;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class StockForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Produit')
                    ->tabs([
                        Tabs\Tab::make('Identification')
                            ->icon('heroicon-o-identification')
                            ->schema([
                                Section::make('Informations')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Désignation')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('Ex : Aliment démarrage poussin 1ᵉʳ âge'),

                                        TextInput::make('code')
                                            ->label('Code produit')
                                            ->maxLength(50)
                                            ->disabled()
                                            ->dehydrated()
                                            ->placeholder('Généré automatiquement (STK-XXXXX)'),

                                        Select::make('stock_category_id')
                                            ->label('Catégorie')
                                            ->options(function (): array {
                                                $tenant = Filament::getTenant();

                                                return StockCategory::query()
                                                    ->where('organisation_id', $tenant?->getKey())
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id')
                                                    ->toArray();
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->nullable(),

                                        Select::make('farm_id')
                                            ->label('Ferme')
                                            ->helperText('Laisser vide pour un stock organisation')
                                            ->options(function (): array {
                                                $tenant = Filament::getTenant();

                                                return Farm::query()
                                                    ->where('organisation_id', $tenant?->getKey())
                                                    ->tap(fn ($q) => app(CurrentFarm::class)->restrictForCurrentUser($q, 'id'))
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id')
                                                    ->toArray();
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->nullable(),

                                        Textarea::make('description')
                                            ->label('Description')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Quantités & prix')
                            ->icon('heroicon-o-scale')
                            ->schema([
                                Section::make('Unité & seuil')
                                    ->columns(3)
                                    ->schema([
                                        TextInput::make('unit')
                                            ->label('Unité de mesure')
                                            ->required()
                                            ->maxLength(30)
                                            ->placeholder('kg, L, sac, dose, unité'),

                                        TextInput::make('quantity')
                                            ->label('Quantité en stock')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->disabled()
                                            ->dehydrated()
                                            ->helperText('Mise à jour par les mouvements'),

                                        TextInput::make('min_quantity')
                                            ->label('Seuil d\'alerte')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->helperText('Déclenche une alerte "stock bas"'),
                                    ]),

                                Section::make('Valorisation')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('unit_price')
                                            ->label('Prix unitaire moyen')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->suffix('XOF'),

                                        DatePicker::make('expiry_date')
                                            ->label('Date de péremption (produit)')
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->helperText('Utilisez plutôt les dates par lot pour la traçabilité'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Emplacement & paramètres')
                            ->icon('heroicon-o-adjustments-horizontal')
                            ->schema([
                                Section::make()
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('location')
                                            ->label('Emplacement')
                                            ->maxLength(255)
                                            ->placeholder('Ex : Magasin A, étagère 3'),

                                        Toggle::make('is_active')
                                            ->label('Actif')
                                            ->default(true),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
