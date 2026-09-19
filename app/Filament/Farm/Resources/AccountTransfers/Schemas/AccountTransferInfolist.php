<?php

namespace App\Filament\Farm\Resources\AccountTransfers\Schemas;

use App\Models\AccountTransfer;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AccountTransferInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Transfert')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('reference')
                            ->label('Référence')
                            ->badge()
                            ->color('primary'),

                        TextEntry::make('transfer_date')
                            ->label('Date')
                            ->date('d/m/Y'),

                        TextEntry::make('status')
                            ->label('Statut')
                            ->badge(),

                        TextEntry::make('user.name')
                            ->label('Effectué par')
                            ->placeholder('—'),
                    ]),

                Section::make('Comptes')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('fromAccount.name')
                            ->label('Compte source')
                            ->icon('heroicon-o-arrow-up-circle')
                            ->color('danger'),

                        TextEntry::make('toAccount.name')
                            ->label('Compte destinataire')
                            ->icon('heroicon-o-arrow-down-circle')
                            ->color('success'),
                    ]),

                Section::make('Montants')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('amount')
                            ->label('Montant transféré')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF')
                            ->weight('bold'),

                        TextEntry::make('fees')
                            ->label('Frais')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF')
                            ->color('warning'),

                        TextEntry::make('total_debited')
                            ->label('Total débité')
                            ->state(fn (AccountTransfer $record): float => $record->getTotalAmount())
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF')
                            ->weight('bold')
                            ->color('primary'),
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
