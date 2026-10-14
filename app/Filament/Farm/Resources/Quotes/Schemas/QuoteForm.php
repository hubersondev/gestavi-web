<?php

namespace App\Filament\Farm\Resources\Quotes\Schemas;

use App\Enums\QuoteStatus;
use App\Filament\Farm\Components\FiscalYearDatePicker;
use App\Models\Customer;
use App\Models\Farm;
use App\Models\Flock;
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
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class QuoteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Devis')
                    ->tabs([
                        Tabs\Tab::make('Informations générales')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Section::make('Identification')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('quote_number')
                                            ->label('N° Devis')
                                            ->maxLength(50)
                                            ->disabled()
                                            ->dehydrated()
                                            ->placeholder('Généré automatiquement (DEV-YYYY-XXXXX)'),

                                        Select::make('farm_id')
                                            // Garde-fou serveur : la valeur soumise doit appartenir à l'organisation courante
                                            ->rules(fn (): array => [Rule::exists('farms', 'id')->where('organisation_id', Filament::getTenant()?->getKey())])
                                            ->label('Ferme')
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

                                        Select::make('customer_id')
                                            // Garde-fou serveur : la valeur soumise doit appartenir à l'organisation courante
                                            ->rules(fn (): array => [Rule::exists('customers', 'id')->where('organisation_id', Filament::getTenant()?->getKey())])
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
                                            ->helperText('Optionnel — saisir client occasionnel ci-dessous si absent'),

                                        TextInput::make('reference')
                                            ->label('Référence client')
                                            ->maxLength(255),
                                    ]),

                                Section::make('Dates')
                                    ->columns(2)
                                    ->schema([
                                        FiscalYearDatePicker::make('quote_date')
                                            ->label('Date du devis')
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->required(),

                                        DatePicker::make('valid_until')
                                            ->label('Valide jusqu\'au')
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->default(now()->addDays(30)),
                                    ]),

                                Section::make('Client occasionnel')
                                    ->description('Pour un devis sans client enregistré')
                                    ->collapsed()
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('client_name')
                                            ->label('Nom')
                                            ->maxLength(255),

                                        TextInput::make('client_phone')
                                            ->label('Téléphone')
                                            ->tel()
                                            ->maxLength(30),

                                        TextInput::make('client_email')
                                            ->label('Email')
                                            ->email()
                                            ->maxLength(255),

                                        Textarea::make('client_address')
                                            ->label('Adresse')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Articles')
                            ->icon('heroicon-o-shopping-cart')
                            ->schema([
                                Section::make('Lignes du devis')
                                    ->schema([
                                        Repeater::make('items')
                                            ->label('')
                                            ->schema([
                                                Hidden::make('id'),

                                                Select::make('product_id')
                                                    ->label('Produit du catalogue')
                                                    ->options(fn (): array => \App\Models\Product::query()
                                                        ->where('organisation_id', Filament::getTenant()?->getKey())
                                                        ->active()
                                                        ->orderBy('name')
                                                        ->pluck('name', 'id')
                                                        ->toArray())
                                                    ->rules(fn (): array => [Rule::exists('products', 'id')->where('organisation_id', Filament::getTenant()?->getKey())])
                                                    ->searchable()
                                                    ->nullable()
                                                    ->live()
                                                    ->afterStateUpdated(function (\Filament\Schemas\Components\Utilities\Set $set, ?string $state): void {
                                                        $product = $state ? \App\Models\Product::query()->find($state) : null;

                                                        if (! $product) {
                                                            return;
                                                        }

                                                        foreach (['product_name', 'unit', 'unit_price'] as $field) {
                                                            $set($field, $product->lineDefaults()[$field]);
                                                        }
                                                    })
                                                    ->helperText('Pré-remplit la ligne ; la saisie libre reste possible')
                                                    ->columnSpan(2),

                                                TextInput::make('product_name')
                                                    ->label('Désignation')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->columnSpan(3),

                                                Select::make('flock_id')
                                                    // Garde-fou serveur : la valeur soumise doit appartenir à l'organisation courante
                                                    ->rules(fn (): array => [Rule::exists('flocks', 'id')->where('organisation_id', Filament::getTenant()?->getKey())])
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
                                                    ->columnSpan(2),

                                                TextInput::make('unit')
                                                    ->label('Unité')
                                                    ->default('unité')
                                                    ->maxLength(30)
                                                    ->columnSpan(1),

                                                TextInput::make('quantity')
                                                    ->label('Qté')
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

                        Tabs\Tab::make('Remises & statut')
                            ->icon('heroicon-o-calculator')
                            ->schema([
                                Section::make('Remises et taxes')
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
                                            ->suffix('%'),
                                    ]),

                                Section::make('Statut')
                                    ->schema([
                                        Select::make('status')
                                            ->label('Statut du devis')
                                            ->options(QuoteStatus::class)
                                            ->default(QuoteStatus::Draft)
                                            ->required()
                                            ->native(false),
                                    ]),
                            ]),

                        Tabs\Tab::make('Notes & conditions')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        Textarea::make('terms_conditions')
                                            ->label('Conditions générales')
                                            ->rows(4)
                                            ->placeholder('Ex : Délai de livraison, modalités de paiement…')
                                            ->columnSpanFull(),

                                        Textarea::make('notes')
                                            ->label('Notes visibles sur le devis')
                                            ->rows(3)
                                            ->columnSpanFull(),

                                        Textarea::make('internal_notes')
                                            ->label('Notes internes (non visibles)')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
