<?php

namespace App\Filament\Farm\Resources\StockLots\Schemas;

use App\Models\StockLot;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StockLotInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identification')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('lot_number')->label('N° Lot')->badge()->color('primary'),
                        TextEntry::make('internal_lot_number')->label('N° interne')->placeholder('—'),
                        TextEntry::make('stock.name')->label('Produit')->weight('bold'),
                        TextEntry::make('status')->label('Statut')->badge(),
                    ]),

                Section::make('Dates')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('reception_date')->label('Reçu le')->date('d/m/Y'),
                        TextEntry::make('manufacturing_date')->label('Fabriqué le')->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('expiry_date')->label('Périme le')->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('supplier.name')->label('Fournisseur')->placeholder('—'),
                    ]),

                Section::make('Quantités & valorisation')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('initial_quantity')
                            ->label('Qté initiale')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(fn (StockLot $record): string => ' '.$record->stock?->unit),

                        TextEntry::make('current_quantity')
                            ->label('Qté restante')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(fn (StockLot $record): string => ' '.$record->stock?->unit)
                            ->weight('bold'),

                        TextEntry::make('unit_price')
                            ->label('PU')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF'),

                        TextEntry::make('total_value')
                            ->label('Valeur totale')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF')
                            ->color('primary')
                            ->weight('bold'),
                    ]),

                Section::make('Localisation & qualité')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('location')->label('Emplacement')->placeholder('—'),
                        TextEntry::make('certificate_number')->label('Certificat d\'analyse')->placeholder('—'),
                        TextEntry::make('notes')->label('Notes')->placeholder('—')->columnSpanFull(),
                    ]),
            ]);
    }
}
