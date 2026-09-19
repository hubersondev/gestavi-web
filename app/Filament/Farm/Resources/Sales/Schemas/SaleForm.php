<?php

namespace App\Filament\Farm\Resources\Sales\Schemas;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\Quote;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class SaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Facture')
                    ->tabs([

                        Tabs\Tab::make('Informations générales')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Section::make('Identification')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('invoice_number')
                                            ->label('N° Facture')
                                            ->maxLength(50)
                                            ->disabled()
                                            ->dehydrated()
                                            ->placeholder('Généré automatiquement (FAC-YYYY-XXXXX)'),

                                        Select::make('farm_id')
                                            ->label('Ferme')
                                            ->helperText('Optionnel — laisser vide pour une vente de niveau organisation')
                                            ->options(function (): array {
                                                $tenant = Filament::getTenant();

                                                if (! $tenant) {
                                                    return [];
                                                }

                                                return Farm::query()
                                                    ->where('organisation_id', $tenant->getKey())
                                                    ->tap(fn ($q) => app(CurrentFarm::class)->restrictForCurrentUser($q, 'id'))
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id')
                                                    ->toArray();
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->nullable(),

                                        Select::make('customer_id')
                                            ->label('Client')
                                            ->options(function (): array {
                                                $tenant = Filament::getTenant();

                                                return Customer::query()
                                                    ->where('organisation_id', $tenant?->getKey())
                                                    ->tap(fn ($q) => app(CurrentFarm::class)->restrictForCurrentUser($q))
                                                    ->active()
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id')
                                                    ->toArray();
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->helperText('Laisser vide pour une vente comptant'),

                                        Select::make('quote_id')
                                            ->label('Devis lié')
                                            ->options(function (): array {
                                                $tenant = Filament::getTenant();

                                                return Quote::query()
                                                    ->where('organisation_id', $tenant?->getKey())
                                                    ->accepted()
                                                    ->orderByDesc('quote_date')
                                                    ->pluck('quote_number', 'id')
                                                    ->toArray();
                                            })
                                            ->searchable()
                                            ->nullable()
                                            ->helperText('Optionnel'),
                                    ]),

                                Section::make('Dates')
                                    ->columns(2)
                                    ->schema([
                                        DatePicker::make('sale_date')
                                            ->label('Date de vente')
                                            ->required()
                                            ->default(now())
                                            ->native(false)
                                            ->displayFormat('d/m/Y'),

                                        DatePicker::make('due_date')
                                            ->label('Date d\'échéance')
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->helperText('Pour les ventes à crédit')
                                            ->afterOrEqual('sale_date'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Articles')
                            ->icon('heroicon-o-shopping-cart')
                            ->schema([
                                Section::make('Lignes de vente')
                                    ->schema([
                                        Repeater::make('items')
                                            ->label('')
                                            ->relationship()
                                            ->schema([
                                                TextInput::make('product_name')
                                                    ->label('Désignation')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->columnSpan(3),

                                                Select::make('flock_id')
                                                    ->label('Bande liée')
                                                    ->options(function (): array {
                                                        $tenant = Filament::getTenant();

                                                        return Flock::query()
                                                            ->whereHas('building.farm', fn ($q) => app(CurrentFarm::class)->restrictFarmsTableForCurrentUser($q->where('organisation_id', $tenant?->getKey())))
                                                            ->orderByDesc('start_date')
                                                            ->get()
                                                            ->mapWithKeys(fn (Flock $flock) => [$flock->id => $flock->code.' – '.$flock->name])
                                                            ->toArray();
                                                    })
                                                    ->searchable()
                                                    ->nullable()
                                                    ->live()
                                                    ->helperText('Optionnel')
                                                    ->columnSpan(2),

                                                \Filament\Forms\Components\Toggle::make('is_live_birds')
                                                    ->label('Sujets vivants')
                                                    ->helperText('Décrémente l\'effectif de la bande liée')
                                                    ->default(false)
                                                    ->visible(fn (Get $get): bool => filled($get('flock_id')))
                                                    ->columnSpan(1),

                                                TextInput::make('unit')
                                                    ->label('Unité')
                                                    ->default('unité')
                                                    ->maxLength(30)
                                                    ->columnSpan(1),

                                                TextInput::make('quantity')
                                                    ->label('Quantité')
                                                    ->numeric()
                                                    ->required()
                                                    ->default(1)
                                                    ->minValue(0.01)
                                                    ->live(onBlur: true)
                                                    ->columnSpan(1),

                                                TextInput::make('unit_price')
                                                    ->label('Prix unitaire')
                                                    ->numeric()
                                                    ->required()
                                                    ->minValue(0)
                                                    ->suffix('XOF')
                                                    ->live(onBlur: true)
                                                    ->columnSpan(2),

                                                TextInput::make('discount_percent')
                                                    ->label('Remise %')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->minValue(0)
                                                    ->maxValue(100)
                                                    ->suffix('%')
                                                    ->live(onBlur: true)
                                                    ->columnSpan(1),

                                                Placeholder::make('line_total')
                                                    ->label('Total ligne')
                                                    ->content(function (Get $get): string {
                                                        $qty = (float) ($get('quantity') ?? 0);
                                                        $price = (float) ($get('unit_price') ?? 0);
                                                        $discount = (float) ($get('discount_percent') ?? 0);
                                                        $subtotal = $qty * $price;
                                                        $total = $subtotal - ($subtotal * $discount / 100);

                                                        return number_format($total, 0, ',', ' ').' XOF';
                                                    })
                                                    ->columnSpan(2),

                                                Textarea::make('description')
                                                    ->label('Description')
                                                    ->rows(2)
                                                    ->columnSpan(12),
                                            ])
                                            ->columns(12)
                                            ->addActionLabel('Ajouter un article')
                                            ->reorderable()
                                            ->collapsible()
                                            ->cloneable()
                                            ->defaultItems(1)
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Totaux & remises')
                            ->icon('heroicon-o-calculator')
                            ->schema([
                                Section::make('Remises et taxes globales')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('discount_percent')
                                            ->label('Remise globale (%)')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->suffix('%'),

                                        TextInput::make('tax_percent')
                                            ->label('TVA (%)')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->suffix('%')
                                            ->helperText('Souvent 0 en Côte d\'Ivoire pour les produits agricoles frais'),
                                    ]),

                                Section::make('Récapitulatif')
                                    ->description('Recalculé automatiquement après enregistrement')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('subtotal')
                                            ->label('Sous-total')
                                            ->numeric()
                                            ->default(0)
                                            ->suffix('XOF')
                                            ->disabled()
                                            ->dehydrated(false),

                                        TextInput::make('total')
                                            ->label('TOTAL')
                                            ->numeric()
                                            ->default(0)
                                            ->suffix('XOF')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->extraAttributes(['class' => 'font-bold text-lg']),
                                    ]),
                            ]),

                        Tabs\Tab::make('Paiement')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Section::make('Encaissement')
                                    ->columns(2)
                                    ->schema([
                                        Select::make('status')
                                            ->label('Statut')
                                            ->options([
                                                SaleStatus::Draft->value => SaleStatus::Draft->getLabel(),
                                                SaleStatus::Pending->value => SaleStatus::Pending->getLabel(),
                                            ])
                                            ->default(SaleStatus::Draft)
                                            ->required()
                                            ->native(false)
                                            ->disabledOn('edit')
                                            ->helperText('Partiel / Payée / Annulée sont pilotés par les paiements et l\'annulation.'),

                                        Select::make('payment_method')
                                            ->label('Mode de paiement principal')
                                            ->options(PaymentMethod::class)
                                            ->native(false)
                                            ->nullable()
                                            ->helperText('Mode de paiement prévu (informatif)'),

                                        Select::make('account_id')
                                            ->label('Compte d\'encaissement')
                                            ->options(function (): array {
                                                $tenant = Filament::getTenant();

                                                return Account::query()
                                                    ->where('organisation_id', $tenant?->getKey())
                                                    ->tap(fn ($q) => app(CurrentFarm::class)->restrictForCurrentUser($q))
                                                    ->active()
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id')
                                                    ->toArray();
                                            })
                                            ->searchable()
                                            ->nullable()
                                            ->helperText('Compte sur lequel enregistrer les paiements')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Notes')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        Textarea::make('notes')
                                            ->label('Notes')
                                            ->rows(4)
                                            ->placeholder('Remarques, conditions particulières…')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
