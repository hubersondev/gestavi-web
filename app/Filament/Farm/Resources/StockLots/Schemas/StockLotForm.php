<?php

namespace App\Filament\Farm\Resources\StockLots\Schemas;

use App\Enums\StockLotStatus;
use App\Models\Stock;
use App\Models\Supplier;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class StockLotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identification')
                    ->columns(2)
                    ->schema([
                        Select::make('stock_id')
                            // Garde-fou serveur : la valeur soumise doit appartenir à l'organisation courante
                            ->rules(fn (): array => [Rule::exists('stocks', 'id')->where('organisation_id', Filament::getTenant()?->getKey())])
                            ->label('Produit')
                            ->options(function (): array {
                                $tenant = Filament::getTenant();

                                return Stock::query()
                                    ->where('organisation_id', $tenant?->getKey())
                                    ->tap(fn ($q) => app(CurrentFarm::class)->restrictForCurrentUser($q))
                                    ->active()
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabledOn('edit'),

                        Select::make('supplier_id')
                            // Garde-fou serveur : la valeur soumise doit appartenir à l'organisation courante
                            ->rules(fn (): array => [Rule::exists('suppliers', 'id')->where(fn ($q) => $q->where('organisation_id', Filament::getTenant()?->getKey())->orWhereNull('organisation_id'))])
                            ->label('Fournisseur')
                            ->options(function (): array {
                                $tenant = Filament::getTenant();

                                return Supplier::query()
                                    ->where('organisation_id', $tenant?->getKey())
                                    ->tap(fn ($q) => app(CurrentFarm::class)->restrictForCurrentUser($q, 'farm_id', allowOrganisationLevel: true))
                                    ->where('is_active', true)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->searchable()
                            ->nullable(),

                        TextInput::make('lot_number')
                            ->label('N° de lot fournisseur')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('internal_lot_number')
                            ->label('N° interne')
                            ->maxLength(255),
                    ]),

                Section::make('Dates & durée de conservation')
                    ->columns(3)
                    ->schema([
                        DatePicker::make('reception_date')
                            ->label('Date de réception')
                            ->default(now())
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        DatePicker::make('manufacturing_date')
                            ->label('Date de fabrication')
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        DatePicker::make('expiry_date')
                            ->label('Date de péremption')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ]),

                Section::make('Quantités & prix')
                    ->columns(3)
                    ->schema([
                        TextInput::make('initial_quantity')
                            ->label('Quantité initiale')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->disabledOn('edit'),

                        TextInput::make('current_quantity')
                            ->label('Quantité actuelle')
                            ->numeric()
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Mise à jour par les mouvements'),

                        TextInput::make('unit_price')
                            ->label('Prix unitaire')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->suffix('XOF'),
                    ]),

                Section::make('Qualité & emplacement')
                    ->columns(3)
                    ->schema([
                        Select::make('status')
                            ->label('Statut')
                            ->options(StockLotStatus::class)
                            ->default(StockLotStatus::Available)
                            ->required()
                            ->native(false),

                        TextInput::make('location')
                            ->label('Emplacement')
                            ->maxLength(255),

                        TextInput::make('certificate_number')
                            ->label('N° certificat d\'analyse')
                            ->maxLength(255),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
