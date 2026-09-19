<?php

namespace App\Filament\Farm\Resources\PurchaseOrders\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PurchaseOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Bon de commande')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('order_number')->label('N° BC')->badge()->color('primary')->weight('bold'),
                        TextEntry::make('order_date')->label('Date')->date('d/m/Y'),
                        TextEntry::make('status')->label('Statut')->badge(),
                        TextEntry::make('supplier.name')->label('Fournisseur')->weight('bold'),
                    ]),

                Section::make('Livraison')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('expected_delivery_date')->label('Livraison prévue')->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('actual_delivery_date')->label('Livraison réelle')->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('delivery_contact')->label('Contact')->placeholder('—'),
                        TextEntry::make('delivery_address')->label('Adresse')->placeholder('—')->columnSpanFull(),
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

                                TextEntry::make('quantity_ordered')
                                    ->label('Commandée')
                                    ->suffix(fn ($record): string => ' '.$record->unit),

                                TextEntry::make('quantity_received')
                                    ->label('Reçue')
                                    ->suffix(fn ($record): string => ' '.$record->unit)
                                    ->color('success'),

                                TextEntry::make('unit_price')
                                    ->label('PU')
                                    ->numeric(thousandsSeparator: ' ')
                                    ->suffix(' XOF'),

                                TextEntry::make('total')
                                    ->label('Total')
                                    ->numeric(thousandsSeparator: ' ')
                                    ->suffix(' XOF')
                                    ->weight('bold'),
                            ]),
                    ]),

                Section::make('Totaux')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('subtotal')
                            ->label('Sous-total')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF'),

                        TextEntry::make('shipping_cost')
                            ->label('Livraison')
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
                    ]),

                Section::make('Conditions & notes')
                    ->schema([
                        TextEntry::make('payment_terms')->label('Conditions paiement')->badge(),
                        TextEntry::make('notes')->label('Notes')->placeholder('—')->columnSpanFull(),
                    ]),
            ]);
    }
}
