<?php

namespace App\Filament\Farm\Resources\StockInventories\Schemas;

use App\Models\StockInventory;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StockInventoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Inventaire')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('inventory_ref')->label('Référence')->badge()->color('primary')->weight('bold'),
                        TextEntry::make('inventory_date')->label('Date')->date('d/m/Y'),
                        TextEntry::make('status')->label('Statut')->badge(),
                        TextEntry::make('stock.name')->label('Produit')->weight('bold'),
                    ]),

                Section::make('Quantités')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('theoretical_quantity')
                            ->label('Théorique')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(fn (StockInventory $record): string => ' '.$record->stock?->unit),

                        TextEntry::make('physical_quantity')
                            ->label('Physique')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(fn (StockInventory $record): string => ' '.$record->stock?->unit),

                        TextEntry::make('difference')
                            ->label('Écart')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(fn (StockInventory $record): string => ' '.$record->stock?->unit)
                            ->weight('bold')
                            ->color(fn (StockInventory $record): string => $record->hasDifference() ? ($record->isPositiveDifference() ? 'success' : 'danger') : 'gray'),

                        TextEntry::make('difference_percent')
                            ->label('Écart %')
                            ->suffix(' %')
                            ->placeholder('—'),
                    ]),

                Section::make('Valorisation')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('unit_price')
                            ->label('PU')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF'),

                        TextEntry::make('difference_value')
                            ->label('Valeur de l\'écart')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF')
                            ->color(fn (StockInventory $record): string => $record->isPositiveDifference() ? 'success' : ((float) $record->difference_value < 0 ? 'danger' : 'gray'))
                            ->weight('bold'),
                    ]),

                Section::make('Justification')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('difference_reason')->label('Motif')->badge()->placeholder('—'),
                        TextEntry::make('conductor.name')->label('Compté par')->placeholder('—'),
                        TextEntry::make('validator.name')->label('Validé par')->placeholder('—'),
                        TextEntry::make('validated_at')->label('Validé le')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('justification')->label('Justification')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('notes')->label('Notes')->placeholder('—')->columnSpanFull(),
                    ]),

                Section::make('Mouvement d\'ajustement')
                    ->hidden(fn (StockInventory $record): bool => $record->adjustment_movement_id === null)
                    ->schema([
                        TextEntry::make('adjustmentMovement.id')
                            ->label('Mouvement créé')
                            ->formatStateUsing(fn ($state): string => '#'.str_pad((string) $state, 6, '0', STR_PAD_LEFT))
                            ->badge()
                            ->color('success'),
                    ]),
            ]);
    }
}
