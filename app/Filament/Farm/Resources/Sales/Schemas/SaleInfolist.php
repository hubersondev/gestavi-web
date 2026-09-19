<?php

namespace App\Filament\Farm\Resources\Sales\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SaleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Facture')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('invoice_number')
                            ->label('N° Facture')
                            ->badge()
                            ->color('primary')
                            ->weight('bold'),

                        TextEntry::make('sale_date')
                            ->label('Date')
                            ->date('d/m/Y'),

                        TextEntry::make('due_date')
                            ->label('Échéance')
                            ->date('d/m/Y')
                            ->placeholder('—'),

                        TextEntry::make('status')
                            ->label('Statut')
                            ->badge(),
                    ]),

                Section::make('Client & références')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('customer.name')
                            ->label('Client')
                            ->icon('heroicon-o-user')
                            ->placeholder('Vente comptant'),

                        TextEntry::make('quote.quote_number')
                            ->label('Devis d\'origine')
                            ->badge()
                            ->color('info')
                            ->placeholder('—'),

                        TextEntry::make('seller.name')
                            ->label('Vendeur')
                            ->placeholder('—'),
                    ]),

                Section::make('Lignes de vente')
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
                                    ->label('Qté')
                                    ->suffix(fn ($record): string => ' '.$record->unit),

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
                            ->label('Remise globale')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF'),

                        TextEntry::make('tax_amount')
                            ->label('TVA')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF'),

                        TextEntry::make('total')
                            ->label('TOTAL')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF')
                            ->weight('bold')
                            ->size('lg')
                            ->color('primary'),

                        TextEntry::make('paid')
                            ->label('Payé')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF')
                            ->color('success'),

                        TextEntry::make('balance')
                            ->label('Solde dû')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF')
                            ->weight('bold')
                            ->color(fn ($state): string => (float) $state > 0 ? 'danger' : 'success'),
                    ]),

                Section::make('Notes')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
