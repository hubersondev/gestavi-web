<?php

namespace App\Filament\Farm\Resources\Suppliers\Schemas;

use App\Models\Farm;
use App\Models\Supplier;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class SupplierForm
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
                                            ->label('Raison sociale')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('Ex: Ivoire Aliments SA'),

                                        TextInput::make('code')
                                            ->label('Code fournisseur')
                                            ->placeholder('Généré automatiquement')
                                            ->maxLength(50)
                                            ->unique(ignoreRecord: true)
                                            ->disabled()
                                            ->dehydrated(),

                                        Select::make('type')
                                            ->label('Type de fournisseur')
                                            ->required()
                                            ->options(Supplier::typeOptions())
                                            ->native(false)
                                            ->searchable(),

                                        Select::make('parent_supplier_id')
                                            ->label('Réseau fournisseur')
                                            ->helperText('Regrouper ce fournisseur sous un réseau existant (optionnel)')
                                            ->options(function () {
                                                $tenant = Filament::getTenant();

                                                return Supplier::query()
                                                    ->where('organisation_id', $tenant?->getKey())
                                                    ->whereNull('farm_id')
                                                    ->whereNull('parent_supplier_id')
                                                    ->active()
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id');
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->placeholder('Aucun (fournisseur indépendant)')
                                            ->nullable(),

                                        Select::make('farm_id')
                                            ->label('Ferme')
                                            ->helperText('Laisser vide pour un fournisseur réseau (toutes fermes)')
                                            ->options(function () {
                                                $tenant = Filament::getTenant();
                                                $user = Filament::auth()->user();
                                                $membership = $user?->membershipIn($tenant);

                                                return Farm::query()
                                                    ->where('organisation_id', $tenant?->getKey())
                                                    ->when($membership, fn (Builder $q) => $q->accessibleBy($membership))
                                                    ->pluck('name', 'id');
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->placeholder('Toutes les fermes (réseau)')
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
                                            }),

                                        Toggle::make('is_active')
                                            ->label('Actif')
                                            ->default(true)
                                            ->helperText('Désactiver pour masquer ce fournisseur des sélections'),
                                    ])
                                    ->columns(2),
                            ]),

                        Tabs\Tab::make('Contact')
                            ->icon('heroicon-o-phone')
                            ->schema([
                                Section::make('Personne de contact')
                                    ->schema([
                                        TextInput::make('contact_name')
                                            ->label('Nom du contact')
                                            ->maxLength(255)
                                            ->placeholder('Ex: Kouamé Koffi'),

                                        TextInput::make('phone')
                                            ->label('Téléphone principal')
                                            ->tel()
                                            ->maxLength(50)
                                            ->placeholder('Ex: +225 07 00 00 00'),

                                        TextInput::make('phone_secondary')
                                            ->label('Téléphone secondaire')
                                            ->tel()
                                            ->maxLength(50)
                                            ->placeholder('Optionnel'),

                                        TextInput::make('email')
                                            ->label('Email')
                                            ->email()
                                            ->maxLength(255)
                                            ->placeholder('Ex: contact@fournisseur.ci'),
                                    ])
                                    ->columns(2),

                                Section::make('Adresse')
                                    ->schema([
                                        Textarea::make('address')
                                            ->label('Adresse')
                                            ->rows(2)
                                            ->placeholder('Adresse complète...')
                                            ->columnSpanFull(),

                                        Select::make('country_id')
                                            ->label('Pays')
                                            ->relationship('country', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(fn (callable $set) => $set('city_id', null))
                                            ->placeholder('Sélectionner un pays'),

                                        Select::make('city_id')
                                            ->label('Ville')
                                            ->relationship('city', 'name', function (Builder $query, Get $get): void {
                                                if ($get('country_id')) {
                                                    $query->where('country_id', $get('country_id'));
                                                }
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->placeholder('Sélectionner une ville'),
                                    ])
                                    ->columns(2),
                            ]),

                        Tabs\Tab::make('Finances')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Section::make('Informations fiscales')
                                    ->schema([
                                        TextInput::make('tax_id')
                                            ->label('N° contribuable / CC')
                                            ->maxLength(100)
                                            ->placeholder('Numéro d\'identification fiscale'),
                                    ]),

                                Section::make('Informations bancaires')
                                    ->schema([
                                        TextInput::make('bank_name')
                                            ->label('Banque')
                                            ->maxLength(255)
                                            ->placeholder('Ex: SGBCI, BICICI...'),

                                        TextInput::make('bank_account')
                                            ->label('N° de compte / IBAN')
                                            ->maxLength(100)
                                            ->placeholder('Numéro de compte bancaire'),

                                        TextInput::make('payment_terms')
                                            ->label('Conditions de paiement')
                                            ->maxLength(255)
                                            ->placeholder('Ex: 30 jours net, à la livraison...'),
                                    ])
                                    ->columns(3),
                            ]),

                        Tabs\Tab::make('Notes')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make('Notes internes')
                                    ->schema([
                                        Textarea::make('notes')
                                            ->label('Notes')
                                            ->rows(4)
                                            ->placeholder('Informations complémentaires sur ce fournisseur...'),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
