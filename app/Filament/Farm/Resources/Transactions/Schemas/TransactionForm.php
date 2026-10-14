<?php

namespace App\Filament\Farm\Resources\Transactions\Schemas;

use App\Enums\TransactionCategory;
use App\Enums\TransactionType;
use App\Filament\Farm\Components\FiscalYearDatePicker;
use App\Models\Account;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class TransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Transaction')
                    ->tabs([

                        Tabs\Tab::make('Informations générales')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make('Compte concerné')
                                    ->schema([
                                        Select::make('account_id')
                                            // Garde-fou serveur : la valeur soumise doit appartenir à l'organisation courante
                                            ->rules(fn (): array => [Rule::exists('accounts', 'id')->where('organisation_id', Filament::getTenant()?->getKey())])
                                            ->label('Compte')
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
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->live(),

                                        Placeholder::make('account_info')
                                            ->label('Solde actuel')
                                            ->content(function (Get $get): string {
                                                $id = $get('account_id');
                                                if (! $id) {
                                                    return 'Sélectionnez un compte';
                                                }
                                                $account = Account::find($id);
                                                if (! $account) {
                                                    return '—';
                                                }

                                                return $account->type->getLabel().' — '.number_format((float) $account->current_balance, 0, ',', ' ').' XOF';
                                            })
                                            ->visible(fn (Get $get): bool => (bool) $get('account_id')),
                                    ]),

                                Section::make('Détails')
                                    ->columns(2)
                                    ->schema([
                                        Select::make('type')
                                            ->label('Type')
                                            ->options(TransactionType::class)
                                            ->required()
                                            ->native(false)
                                            ->live(),

                                        TextInput::make('amount')
                                            ->label('Montant')
                                            ->numeric()
                                            ->required()
                                            ->minValue(0.01)
                                            ->suffix('XOF'),

                                        Select::make('category')
                                            ->label('Catégorie')
                                            ->options(TransactionCategory::class)
                                            ->required()
                                            ->native(false)
                                            ->searchable(),

                                        FiscalYearDatePicker::make('transaction_date')
                                            ->label('Date')
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->required(),
                                    ]),

                                Section::make('Description')
                                    ->schema([
                                        TextInput::make('description')
                                            ->label('Description')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpanFull(),

                                        TextInput::make('reference_number')
                                            ->label('N° référence externe')
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Rapprochement & notes')
                            ->icon('heroicon-o-check-badge')
                            ->schema([
                                Section::make('Rapprochement bancaire')
                                    ->schema([
                                        Toggle::make('is_reconciled')
                                            ->label('Transaction rapprochée')
                                            ->helperText('Coché si la transaction a été confirmée sur le relevé bancaire'),
                                    ]),

                                Section::make('Notes')
                                    ->schema([
                                        Textarea::make('notes')
                                            ->label('Notes')
                                            ->rows(4)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
