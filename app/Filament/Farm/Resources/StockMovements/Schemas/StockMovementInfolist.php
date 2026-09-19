<?php

namespace App\Filament\Farm\Resources\StockMovements\Schemas;

use App\Models\StockMovement;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StockMovementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Mouvement')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('movement_date')->label('Date')->date('d/m/Y'),
                        TextEntry::make('type')->label('Type')->badge(),
                        TextEntry::make('reason')->label('Motif')->badge(),
                        TextEntry::make('quantity')
                            ->label('Quantité')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(fn (StockMovement $record): string => ' '.$record->stock?->unit)
                            ->weight('bold'),
                    ]),

                Section::make('Détails')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('stock.name')->label('Produit'),
                        TextEntry::make('stockLot.lot_number')->label('Lot')->placeholder('—'),
                        TextEntry::make('flock.code')->label('Bande')->placeholder('—'),
                        TextEntry::make('unit_price')
                            ->label('PU')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF')
                            ->placeholder('—'),
                        TextEntry::make('total_price')
                            ->label('Valeur')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF')
                            ->placeholder('—'),
                        TextEntry::make('user.name')->label('Saisi par')->placeholder('—'),
                    ]),

                Section::make('Notes')
                    ->schema([
                        TextEntry::make('reference')->label('Référence')->placeholder('—'),
                        TextEntry::make('notes')->label('Notes')->placeholder('—')->columnSpanFull(),
                    ]),
            ]);
    }
}
