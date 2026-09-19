<?php

namespace App\Filament\Farm\Resources\Accounts\Schemas;

use App\Enums\AccountType;
use App\Enums\MobileMoneyOperator;
use App\Models\Farm;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class AccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Compte')
                    ->tabs([

                        Tabs\Tab::make('Informations générales')
                            ->icon('heroicon-o-identification')
                            ->schema([
                                Section::make('Identification')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Nom du compte')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('Ex : Caisse principale'),

                                        TextInput::make('code')
                                            ->label('Code')
                                            ->maxLength(50)
                                            ->placeholder('Généré automatiquement')
                                            ->helperText('Laisser vide pour générer CPT-XXX'),

                                        Select::make('type')
                                            ->label('Type')
                                            ->options(AccountType::class)
                                            ->required()
                                            ->native(false)
                                            ->live(),

                                        Select::make('farm_id')
                                            ->label('Ferme associée')
                                            ->helperText('Laisser vide pour un compte de niveau organisation (toutes fermes)')
                                            ->options(function (): array {
                                                $tenant = Filament::getTenant();

                                                if (! $tenant) {
                                                    return [];
                                                }

                                                return Farm::query()
                                                    ->where('organisation_id', $tenant->getKey())
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id')
                                                    ->toArray();
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->nullable(),
                                    ]),

                                Section::make('Solde initial')
                                    ->columns(3)
                                    ->schema([
                                        TextInput::make('initial_balance')
                                            ->label('Solde initial')
                                            ->numeric()
                                            ->default(0)
                                            ->suffix('XOF')
                                            ->helperText('Générera une transaction d\'ouverture si > 0')
                                            ->disabledOn('edit'),

                                        TextInput::make('current_balance')
                                            ->label('Solde actuel')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->suffix('XOF')
                                            ->helperText('Calculé automatiquement'),

                                        Select::make('currency')
                                            ->label('Devise')
                                            ->options([
                                                'XOF' => 'XOF (Franc CFA)',
                                                'EUR' => 'EUR (Euro)',
                                                'USD' => 'USD (Dollar)',
                                            ])
                                            ->default('XOF')
                                            ->native(false)
                                            ->required(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Compte bancaire')
                            ->icon('heroicon-o-building-library')
                            ->visible(fn (Get $get): bool => self::resolveType($get('type')) === AccountType::Bank)
                            ->schema([
                                Section::make('Informations bancaires')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('bank_name')
                                            ->label('Banque')
                                            ->maxLength(255)
                                            ->placeholder('Ex : SGBCI, NSIA Banque'),

                                        TextInput::make('account_number')
                                            ->label('N° de compte')
                                            ->maxLength(50),
                                    ]),
                            ]),

                        Tabs\Tab::make('Mobile Money')
                            ->icon('heroicon-o-device-phone-mobile')
                            ->visible(fn (Get $get): bool => self::resolveType($get('type')) === AccountType::MobileMoney)
                            ->schema([
                                Section::make('Informations Mobile Money')
                                    ->columns(2)
                                    ->schema([
                                        Select::make('operator')
                                            ->label('Opérateur')
                                            ->options(MobileMoneyOperator::class)
                                            ->native(false),

                                        TextInput::make('phone_number')
                                            ->label('Numéro de téléphone')
                                            ->tel()
                                            ->maxLength(20)
                                            ->placeholder('+225 07 00 00 00 00'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Paramètres & notes')
                            ->icon('heroicon-o-adjustments-horizontal')
                            ->schema([
                                Section::make('Options')
                                    ->columns(2)
                                    ->schema([
                                        Toggle::make('is_default')
                                            ->label('Compte par défaut')
                                            ->helperText('Un seul compte par défaut par organisation'),

                                        Toggle::make('is_active')
                                            ->label('Actif')
                                            ->default(true)
                                            ->helperText('Les comptes inactifs ne peuvent plus recevoir de transactions'),
                                    ]),

                                Section::make('Description & notes')
                                    ->schema([
                                        Textarea::make('description')
                                            ->label('Description')
                                            ->rows(2)
                                            ->columnSpanFull(),

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

    /**
     * Normalise la valeur de `type` venant de l'état du formulaire.
     * En création, $get('type') retourne une string ; en édition (via le cast
     * enum du modèle), il peut retourner une instance AccountType.
     */
    private static function resolveType(mixed $value): ?AccountType
    {
        if ($value instanceof AccountType) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return AccountType::tryFrom($value);
        }

        return null;
    }
}
