<?php

namespace App\Filament\Farm\Resources\Stocks\Schemas;

use App\Models\Stock;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StockInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Produit')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('name')->label('Désignation')->weight('bold')->size('lg'),
                        TextEntry::make('code')->label('Code')->badge()->color('gray'),
                        TextEntry::make('category.name')->label('Catégorie')->placeholder('—'),
                        IconEntry::make('is_active')->label('Actif')->boolean(),
                    ]),

                Section::make('Stock actuel')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('quantity')
                            ->label('Quantité')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(fn (Stock $record): string => ' '.$record->unit)
                            ->weight('bold')
                            ->size('lg'),

                        TextEntry::make('min_quantity')
                            ->label('Seuil')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(fn (Stock $record): string => ' '.$record->unit),

                        TextEntry::make('unit_price')
                            ->label('PU moyen')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF'),

                        TextEntry::make('total_value')
                            ->state(fn (Stock $record): float => $record->getTotalValue())
                            ->label('Valeur totale')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF')
                            ->color('primary')
                            ->weight('bold'),
                    ]),

                Section::make('Localisation')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('farm.name')->label('Ferme')->placeholder('Organisation'),
                        TextEntry::make('location')->label('Emplacement')->placeholder('—'),
                        TextEntry::make('expiry_date')->label('Péremption')->date('d/m/Y')->placeholder('—'),
                    ]),

                Section::make('Description')
                    ->schema([
                        TextEntry::make('description')->label('Description')->placeholder('—')->columnSpanFull(),
                    ]),
            ]);
    }
}
