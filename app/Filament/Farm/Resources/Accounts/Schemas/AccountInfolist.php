<?php

namespace App\Filament\Farm\Resources\Accounts\Schemas;

use App\Models\Account;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AccountInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nom')
                            ->weight('bold')
                            ->size('lg'),

                        TextEntry::make('code')
                            ->label('Code')
                            ->badge()
                            ->color('gray')
                            ->placeholder('—'),

                        TextEntry::make('type')
                            ->label('Type')
                            ->badge(),

                        TextEntry::make('farm.name')
                            ->label('Ferme')
                            ->icon('heroicon-o-home')
                            ->placeholder('Organisation (toutes fermes)'),
                    ]),

                Section::make('Soldes')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('initial_balance')
                            ->label('Solde initial')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF'),

                        TextEntry::make('initial_balance_date')
                            ->label('Date du solde initial')
                            ->date('d/m/Y')
                            ->placeholder('—'),

                        TextEntry::make('current_balance')
                            ->label('Solde actuel')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF')
                            ->weight('bold')
                            ->size('lg')
                            ->color(fn ($state): string => (float) $state >= 0 ? 'success' : 'danger'),

                        TextEntry::make('currency')
                            ->label('Devise')
                            ->badge()
                            ->color('info'),
                    ]),

                Section::make('Informations bancaires')
                    ->columns(2)
                    ->visible(fn (Account $record): bool => $record->isBank())
                    ->schema([
                        TextEntry::make('bank_name')
                            ->label('Banque')
                            ->icon('heroicon-o-building-library')
                            ->placeholder('—'),

                        TextEntry::make('account_number')
                            ->label('N° de compte')
                            ->copyable()
                            ->placeholder('—'),
                    ]),

                Section::make('Mobile Money')
                    ->columns(2)
                    ->visible(fn (Account $record): bool => $record->isMobileMoney())
                    ->schema([
                        TextEntry::make('operator')
                            ->label('Opérateur')
                            ->badge(),

                        TextEntry::make('phone_number')
                            ->label('Téléphone')
                            ->icon('heroicon-o-device-phone-mobile')
                            ->copyable()
                            ->placeholder('—'),
                    ]),

                Section::make('Paramètres')
                    ->columns(2)
                    ->schema([
                        IconEntry::make('is_default')
                            ->label('Compte par défaut')
                            ->boolean(),

                        IconEntry::make('is_active')
                            ->label('Actif')
                            ->boolean(),
                    ]),

                Section::make('Description & notes')
                    ->schema([
                        TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
