<?php

namespace App\Filament\Farm\Resources\Quotes\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class QuoteInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Devis')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('quote_number')
                            ->label('N° Devis')
                            ->badge()
                            ->color('primary')
                            ->weight('bold'),

                        TextEntry::make('quote_date')
                            ->label('Date')
                            ->date('d/m/Y'),

                        TextEntry::make('valid_until')
                            ->label('Valide jusqu\'au')
                            ->date('d/m/Y'),

                        TextEntry::make('status')
                            ->label('Statut')
                            ->badge(),
                    ]),

                Section::make('Client')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('customer.name')
                            ->label('Client enregistré')
                            ->placeholder('—'),

                        TextEntry::make('client_name')
                            ->label('Client occasionnel')
                            ->placeholder('—'),

                        TextEntry::make('convertedSale.invoice_number')
                            ->label('Facture créée')
                            ->badge()
                            ->color('success')
                            ->placeholder('—'),
                    ]),

                Section::make('Lignes')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->columns(6)
                            ->schema([
                                TextEntry::make('product_name')
                                    ->label('Désignation')
                                    ->weight('bold')
                                    ->columnSpan(2),

                                TextEntry::make('quantity')
                                    ->label('Qté'),

                                TextEntry::make('unit_price')
                                    ->label('PU')
                                    ->numeric(thousandsSeparator: ' ')
                                    ->suffix(' XOF'),

                                TextEntry::make('discount_percent')
                                    ->label('Remise')
                                    ->suffix(' %'),

                                TextEntry::make('total')
                                    ->label('Total')
                                    ->numeric(thousandsSeparator: ' ')
                                    ->suffix(' XOF')
                                    ->weight('bold'),
                            ]),
                    ]),

                Section::make('Totaux')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('subtotal')
                            ->label('Sous-total')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF'),

                        TextEntry::make('discount_amount')
                            ->label('Remise')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF'),

                        TextEntry::make('total')
                            ->label('TOTAL')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF')
                            ->weight('bold')
                            ->size('lg')
                            ->color('primary'),
                    ]),

                Section::make('Notes')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('terms_conditions')
                            ->label('Conditions générales')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
