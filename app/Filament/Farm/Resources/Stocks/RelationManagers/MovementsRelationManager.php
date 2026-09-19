<?php

namespace App\Filament\Farm\Resources\Stocks\RelationManagers;

use App\Enums\StockMovementReason;
use App\Enums\StockMovementType;
use App\Models\StockMovement;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'movements';

    protected static ?string $title = 'Historique des mouvements';

    protected static ?string $recordTitleAttribute = 'id';

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Mouvement')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('movement_date')->label('Date')->date('d/m/Y'),
                        TextEntry::make('type')->label('Type')->badge(),
                        TextEntry::make('reason')->label('Motif')->badge(),

                        TextEntry::make('quantity')
                            ->label('Quantité')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(fn (StockMovement $record): string => ' '.$record->stock?->unit)
                            ->weight('bold'),

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

                        TextEntry::make('stockLot.lot_number')->label('Lot')->placeholder('—'),
                        TextEntry::make('flock.code')->label('Bande')->placeholder('—'),
                        TextEntry::make('user.name')->label('Utilisateur')->placeholder('—'),

                        TextEntry::make('reference')->label('Référence')->placeholder('—'),
                        TextEntry::make('notes')->label('Notes')->placeholder('—')->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('movement_date', 'desc')
            ->columns([
                TextColumn::make('movement_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge(),

                TextColumn::make('reason')
                    ->label('Motif')
                    ->badge(),

                TextColumn::make('quantity')
                    ->label('Qté')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(fn (StockMovement $record): string => ' '.$record->stock?->unit)
                    ->color(fn (StockMovement $record): string => $record->isIn() ? 'success' : 'danger')
                    ->weight('bold'),

                TextColumn::make('total_price')
                    ->label('Valeur')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' XOF')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('stockLot.lot_number')
                    ->label('Lot')
                    ->badge()
                    ->color('primary')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('flock.code')
                    ->label('Bande')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('user.name')
                    ->label('Utilisateur')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(StockMovementType::class)
                    ->multiple(),

                SelectFilter::make('reason')
                    ->options(StockMovementReason::class)
                    ->multiple(),
            ])
            ->headerActions([])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
