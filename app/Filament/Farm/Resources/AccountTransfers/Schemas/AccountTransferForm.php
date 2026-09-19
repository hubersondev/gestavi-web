<?php

namespace App\Filament\Farm\Resources\AccountTransfers\Schemas;

use App\Models\Account;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class AccountTransferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Transfert')
                    ->tabs([

                        Tabs\Tab::make('Informations générales')
                            ->icon('heroicon-o-arrow-path')
                            ->schema([
                                Section::make('Comptes')
                                    ->columns(2)
                                    ->schema([
                                        Select::make('from_account_id')
                                            ->label('Compte source')
                                            ->options(fn () => self::accountOptions())
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                                if ($get('from_account_id') === $get('to_account_id')) {
                                                    $set('to_account_id', null);
                                                }
                                            }),

                                        Placeholder::make('from_balance')
                                            ->label('Solde disponible')
                                            ->content(function (Get $get): string {
                                                $id = $get('from_account_id');
                                                if (! $id) {
                                                    return '—';
                                                }
                                                $account = Account::find($id);

                                                return $account
                                                    ? number_format((float) $account->current_balance, 0, ',', ' ').' XOF'
                                                    : '—';
                                            }),

                                        Select::make('to_account_id')
                                            ->label('Compte destinataire')
                                            ->options(function (Get $get): array {
                                                return collect(self::accountOptions())
                                                    ->reject(fn ($_, $id) => (int) $id === (int) $get('from_account_id'))
                                                    ->toArray();
                                            })
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->live(),

                                        Placeholder::make('to_balance')
                                            ->label('Solde actuel')
                                            ->content(function (Get $get): string {
                                                $id = $get('to_account_id');
                                                if (! $id) {
                                                    return '—';
                                                }
                                                $account = Account::find($id);

                                                return $account
                                                    ? number_format((float) $account->current_balance, 0, ',', ' ').' XOF'
                                                    : '—';
                                            }),
                                    ]),

                                Section::make('Montant & date')
                                    ->columns(3)
                                    ->schema([
                                        TextInput::make('amount')
                                            ->label('Montant')
                                            ->numeric()
                                            ->required()
                                            ->minValue(1)
                                            ->suffix('XOF'),

                                        TextInput::make('fees')
                                            ->label('Frais')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->suffix('XOF')
                                            ->helperText('Prélevés sur le compte source'),

                                        DatePicker::make('transfer_date')
                                            ->label('Date du transfert')
                                            ->default(now())
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->required(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Référence & notes')
                            ->icon('heroicon-o-pencil-square')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        TextInput::make('reference')
                                            ->label('Référence')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->placeholder('Générée automatiquement (TRF-YYYY-XXXXX)'),

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
     * @return array<int, string>
     */
    private static function accountOptions(): array
    {
        $tenant = Filament::getTenant();

        return Account::query()
            ->where('organisation_id', $tenant?->getKey())
            ->active()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Account $account) => [
                $account->id => $account->name.' ('.number_format((float) $account->current_balance, 0, ',', ' ').' XOF)',
            ])
            ->toArray();
    }
}
