<?php

namespace App\Filament\Farm\Resources\BankReconciliations\Schemas;

use App\Filament\Farm\Components\FiscalYearDatePicker;
use App\Filament\Farm\Resources\Accounts\AccountResource;
use App\Models\Account;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class BankReconciliationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Relevé')
                    ->description('Indiquez le compte, la date de fin du relevé et le solde que la banque, l\'opérateur mobile money ou le comptage de caisse affiche à cette date.')
                    ->columns(2)
                    ->schema([
                        Select::make('account_id')
                            ->label('Compte')
                            ->options(fn (): array => static::accountOptions())
                            // Garde-fou serveur : un compte de l'organisation courante
                            ->rules(fn (): array => [Rule::exists('accounts', 'id')->where('organisation_id', Filament::getTenant()?->getKey())])
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabled(fn (string $operation): bool => $operation === 'edit')
                            ->dehydrated(fn (string $operation): bool => $operation !== 'edit')
                            ->columnSpanFull(),

                        FiscalYearDatePicker::make('statement_date')
                            ->label('Date du relevé')
                            ->maxDate(today())
                            ->required(),

                        TextInput::make('statement_balance')
                            ->label('Solde du relevé à cette date')
                            ->numeric()
                            ->suffix('XOF')
                            ->required(),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Comptes actifs accessibles au membre, avec leur type et leur solde.
     *
     * @return array<int, string>
     */
    private static function accountOptions(): array
    {
        return AccountResource::getEloquentQuery()
            ->active()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Account $account): array => [
                $account->id => sprintf('%s — %s (%s XOF)', $account->name, $account->type?->getLabel() ?? '', number_format((float) $account->current_balance, 0, ',', ' ')),
            ])
            ->all();
    }
}
