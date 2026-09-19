<?php

namespace App\Filament\Farm\Resources\Customers\Schemas;

use App\Enums\CustomerType;
use App\Models\City;
use App\Models\Farm;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Client')
                    ->tabs([
                        Tabs\Tab::make('Identification')
                            ->icon('heroicon-o-identification')
                            ->schema([
                                Section::make('Informations générales')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Nom / Raison sociale')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('Ex : Supermarché Carrefour'),

                                        TextInput::make('code')
                                            ->label('Code client')
                                            ->maxLength(30)
                                            ->disabled()
                                            ->dehydrated()
                                            ->placeholder('Généré automatiquement (CLI-XXXXX)'),

                                        Select::make('type')
                                            ->label('Type de client')
                                            ->options(CustomerType::class)
                                            ->required()
                                            ->native(false),

                                        Select::make('farm_id')
                                            ->label('Ferme')
                                            ->helperText('Laisser vide pour un client organisation (toutes fermes)')
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
                                    ]),
                            ]),

                        Tabs\Tab::make('Contact')
                            ->icon('heroicon-o-phone')
                            ->schema([
                                Section::make('Coordonnées')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('phone')
                                            ->label('Téléphone principal')
                                            ->tel()
                                            ->maxLength(30),

                                        TextInput::make('phone_secondary')
                                            ->label('Téléphone secondaire')
                                            ->tel()
                                            ->maxLength(30),

                                        TextInput::make('email')
                                            ->label('Email')
                                            ->email()
                                            ->maxLength(255),

                                        Select::make('city_id')
                                            ->label('Ville')
                                            ->options(fn () => City::query()->orderBy('name')->pluck('name', 'id')->toArray())
                                            ->searchable()
                                            ->preload()
                                            ->nullable(),
                                    ]),

                                Section::make('Adresse')
                                    ->schema([
                                        Textarea::make('address')
                                            ->label('Adresse complète')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Crédit & notes')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Section::make('Plafond de crédit')
                                    ->description('Le plafond sert à limiter le crédit client. Laisser à 0 pour désactiver.')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('credit_limit')
                                            ->label('Plafond de crédit')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->suffix('XOF'),

                                        TextInput::make('balance')
                                            ->label('Solde actuel')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->suffix('XOF')
                                            ->helperText('Calculé à partir des ventes non réglées'),
                                    ]),

                                Section::make('Paramètres & notes')
                                    ->schema([
                                        Toggle::make('is_active')
                                            ->label('Actif')
                                            ->default(true),

                                        Textarea::make('notes')
                                            ->label('Notes internes')
                                            ->rows(3)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
