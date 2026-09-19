<?php

namespace App\Filament\Farm\Resources\Payments\Schemas;

use App\Enums\PaymentMethod;
use App\Models\Account;
use App\Models\Sale;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Paiement')
                    ->columns(2)
                    ->schema([
                        Select::make('sale_id')
                            ->label('Facture')
                            ->options(function (): array {
                                $tenant = Filament::getTenant();

                                return Sale::query()
                                    ->where('organisation_id', $tenant?->getKey())
                                    ->unpaid()
                                    ->orderByDesc('sale_date')
                                    ->get()
                                    ->mapWithKeys(fn (Sale $sale) => [
                                        $sale->id => "{$sale->invoice_number} — ".number_format((float) $sale->balance, 0, ',', ' ').' XOF',
                                    ])
                                    ->toArray();
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull()
                            ->helperText('Seules les factures avec un solde dû apparaissent'),

                        TextInput::make('amount')
                            ->label('Montant')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->suffix('XOF'),

                        Select::make('method')
                            ->label('Mode de paiement')
                            ->options(PaymentMethod::class)
                            ->required()
                            ->native(false),

                        DatePicker::make('payment_date')
                            ->label('Date du paiement')
                            ->default(now())
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required(),

                        Select::make('account_id')
                            ->label('Compte d\'encaissement')
                            ->options(function (): array {
                                $tenant = Filament::getTenant();

                                return Account::query()
                                    ->where('organisation_id', $tenant?->getKey())
                                    ->active()
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->searchable()
                            ->nullable()
                            ->helperText('Si sélectionné, le compte sera automatiquement crédité'),

                        TextInput::make('reference')
                            ->label('Référence')
                            ->maxLength(255)
                            ->helperText('N° de transaction mobile money, chèque…')
                            ->columnSpanFull(),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
