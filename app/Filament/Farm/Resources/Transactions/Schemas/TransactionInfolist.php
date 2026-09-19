<?php

namespace App\Filament\Farm\Resources\Transactions\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('id')
                            ->label('N°')
                            ->badge()
                            ->color('primary')
                            ->formatStateUsing(fn ($state): string => '#'.str_pad((string) $state, 8, '0', STR_PAD_LEFT)),

                        TextEntry::make('transaction_date')
                            ->label('Date')
                            ->date('d/m/Y'),

                        TextEntry::make('type')
                            ->label('Type')
                            ->badge(),

                        TextEntry::make('amount')
                            ->label('Montant')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF')
                            ->weight('bold')
                            ->size('lg'),
                    ]),

                Section::make('Compte')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('account.name')
                            ->label('Compte')
                            ->icon('heroicon-o-building-library'),

                        TextEntry::make('account.type')
                            ->label('Type compte')
                            ->badge(),

                        TextEntry::make('balance_before')
                            ->label('Solde avant')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF'),

                        TextEntry::make('balance_after')
                            ->label('Solde après')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF')
                            ->weight('bold'),
                    ]),

                Section::make('Catégorie & description')
                    ->schema([
                        TextEntry::make('category')
                            ->label('Catégorie')
                            ->badge(),

                        TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull(),

                        TextEntry::make('reference_number')
                            ->label('N° référence externe')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Rapprochement')
                    ->columns(2)
                    ->schema([
                        IconEntry::make('is_reconciled')
                            ->label('Rapprochée')
                            ->boolean(),

                        TextEntry::make('reconciled_at')
                            ->label('Rapprochée le')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                    ]),

                Section::make('Utilisateur & notes')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('Enregistré par')
                            ->placeholder('—'),

                        TextEntry::make('created_at')
                            ->label('Créée le')
                            ->dateTime('d/m/Y H:i'),

                        TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
