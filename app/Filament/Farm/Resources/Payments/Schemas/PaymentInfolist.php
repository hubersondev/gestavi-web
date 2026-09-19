<?php

namespace App\Filament\Farm\Resources\Payments\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Paiement')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('payment_date')
                            ->label('Date')
                            ->date('d/m/Y'),

                        TextEntry::make('amount')
                            ->label('Montant')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF')
                            ->weight('bold')
                            ->color('success')
                            ->size('lg'),

                        TextEntry::make('method')
                            ->label('Mode')
                            ->badge(),
                    ]),

                Section::make('Facture liée')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('sale.invoice_number')
                            ->label('N° Facture')
                            ->badge()
                            ->color('primary'),

                        TextEntry::make('sale.customer.name')
                            ->label('Client')
                            ->placeholder('Comptant'),

                        TextEntry::make('sale.total')
                            ->label('Total facture')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF'),
                    ]),

                Section::make('Comptabilisation')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('account.name')
                            ->label('Compte crédité')
                            ->icon('heroicon-o-building-library')
                            ->placeholder('Aucun compte lié'),

                        IconEntry::make('is_recorded')
                            ->label('Enregistré en compta')
                            ->boolean(),
                    ]),

                Section::make('Complément')
                    ->schema([
                        TextEntry::make('reference')
                            ->label('Référence')
                            ->placeholder('—'),

                        TextEntry::make('user.name')
                            ->label('Saisi par')
                            ->placeholder('—'),

                        TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
