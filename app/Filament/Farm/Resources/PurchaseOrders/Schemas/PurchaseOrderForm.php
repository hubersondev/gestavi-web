<?php

namespace App\Filament\Farm\Resources\PurchaseOrders\Schemas;

use App\Enums\PaymentTerms;
use App\Enums\PurchaseOrderStatus;
use App\Models\Farm;
use App\Models\Stock;
use App\Models\Supplier;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Bon de commande')
                    ->tabs([
                        Tabs\Tab::make('Informations générales')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Section::make('Identification')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('order_number')
                                            ->label('N° BC')
                                            ->disabled()
                                            ->dehydrated()
                                            ->placeholder('Généré automatiquement (BC-YYYY-XXXXX)'),

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
                                            ->required()
                                            ->searchable()
                                            ->preload(),

                                        Select::make('farm_id')
                                            // Garde-fou serveur : la valeur soumise doit appartenir à l'organisation courante
                                            ->rules(fn (): array => [Rule::exists('farms', 'id')->where('organisation_id', Filament::getTenant()?->getKey())])
                                            ->label('Ferme destinataire')
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

                                        Select::make('status')
                                            ->label('Statut')
                                            ->options(PurchaseOrderStatus::class)
                                            ->default(PurchaseOrderStatus::Draft)
                                            ->required()
                                            ->native(false),
                                    ]),

                                Section::make('Dates')
                                    ->columns(3)
                                    ->schema([
                                        DatePicker::make('order_date')
                                            ->label('Date commande')
                                            ->default(now())
                                            ->required()
                                            ->native(false),

                                        DatePicker::make('expected_delivery_date')
                                            ->label('Livraison prévue')
                                            ->native(false),

                                        DatePicker::make('actual_delivery_date')
                                            ->label('Livraison réelle')
                                            ->native(false),
                                    ]),
                            ]),

                        Tabs\Tab::make('Lignes')
                            ->icon('heroicon-o-shopping-cart')
                            ->schema([
                                Section::make('Articles commandés')
                                    ->schema([
                                        Repeater::make('items')
                                            ->label('')
                                            ->schema([
                                                Hidden::make('id'),

                                                Select::make('stock_id')
                                                    // Garde-fou serveur : la valeur soumise doit appartenir à l'organisation courante
                                                    ->rules(fn (): array => [Rule::exists('stocks', 'id')->where('organisation_id', Filament::getTenant()?->getKey())])
                                                    ->label('Produit existant')
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
                                                    ->searchable()
                                                    ->nullable()
                                                    ->live()
                                                    ->afterStateUpdated(function (Set $set, $state): void {
                                                        if ($state) {
                                                            $stock = Stock::find($state);
                                                            if ($stock) {
                                                                $set('product_name', $stock->name);
                                                                $set('unit', $stock->unit);
                                                                $set('unit_price', $stock->unit_price);
                                                            }
                                                        }
                                                    })
                                                    ->columnSpan(3),

                                                TextInput::make('product_name')
                                                    ->label('Désignation')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->columnSpan(3),

                                                TextInput::make('unit')
                                                    ->label('Unité')
                                                    ->required()
                                                    ->default('unité')
                                                    ->maxLength(30)
                                                    ->columnSpan(1),

                                                TextInput::make('quantity_ordered')
                                                    ->label('Qté commandée')
                                                    ->numeric()
                                                    ->required()
                                                    ->minValue(0.01)
                                                    ->default(1)
                                                    ->live(onBlur: true)
                                                    ->columnSpan(2),

                                                TextInput::make('unit_price')
                                                    ->label('Prix unitaire')
                                                    ->numeric()
                                                    ->required()
                                                    ->minValue(0)
                                                    ->suffix('XOF')
                                                    ->live(onBlur: true)
                                                    ->columnSpan(2),

                                                Placeholder::make('line_total')
                                                    ->label('Total')
                                                    ->content(function (Get $get): string {
                                                        $qty = (float) ($get('quantity_ordered') ?? 0);
                                                        $price = (float) ($get('unit_price') ?? 0);

                                                        return number_format($qty * $price, 0, ',', ' ').' XOF';
                                                    })
                                                    ->columnSpan(1),

                                                Textarea::make('description')
                                                    ->label('Description')
                                                    ->rows(2)
                                                    ->columnSpan(12),
                                            ])
                                            ->columns(12)
                                            ->addActionLabel('Ajouter un article')
                                            ->reorderable()
                                            ->collapsible()
                                            ->defaultItems(1)
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Paiement & livraison')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Section::make('Modalités de paiement')
                                    ->columns(2)
                                    ->schema([
                                        Select::make('payment_terms')
                                            ->label('Conditions')
                                            ->options(PaymentTerms::class)
                                            ->default(PaymentTerms::OnDelivery)
                                            ->native(false),

                                        Textarea::make('payment_terms_details')
                                            ->label('Détails')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('Adresse de livraison')
                                    ->columns(2)
                                    ->schema([
                                        Textarea::make('delivery_address')
                                            ->label('Adresse')
                                            ->rows(2)
                                            ->columnSpanFull(),

                                        TextInput::make('delivery_contact')
                                            ->label('Contact')
                                            ->maxLength(255),

                                        TextInput::make('delivery_phone')
                                            ->label('Téléphone')
                                            ->tel()
                                            ->maxLength(30),
                                    ]),

                                Section::make('Frais additionnels')
                                    ->columns(3)
                                    ->schema([
                                        TextInput::make('shipping_cost')
                                            ->label('Frais de livraison')
                                            ->numeric()
                                            ->default(0)
                                            ->suffix('XOF'),

                                        TextInput::make('discount_amount')
                                            ->label('Remise')
                                            ->numeric()
                                            ->default(0)
                                            ->suffix('XOF'),

                                        TextInput::make('tax_amount')
                                            ->label('TVA')
                                            ->numeric()
                                            ->default(0)
                                            ->suffix('XOF'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Références & notes')
                            ->icon('heroicon-o-pencil-square')
                            ->schema([
                                Section::make()
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('supplier_quote_ref')
                                            ->label('Réf devis fournisseur')
                                            ->maxLength(255),

                                        TextInput::make('supplier_order_ref')
                                            ->label('Réf commande fournisseur')
                                            ->maxLength(255),

                                        Textarea::make('notes')
                                            ->label('Notes (visibles sur le BC)')
                                            ->rows(3)
                                            ->columnSpanFull(),

                                        Textarea::make('internal_notes')
                                            ->label('Notes internes')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
