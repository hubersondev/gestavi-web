<?php

namespace App\Filament\Farm\Resources\Sales\Tables;

use App\Enums\SaleStatus;
use App\Models\Sale;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class SalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sale_date', 'desc')
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('N°')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('sale_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->label('Client')
                    ->searchable()
                    ->placeholder('Comptant')
                    ->sortable(),

                TextColumn::make('total')
                    ->label('Total')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' XOF')
                    ->sortable()
                    ->weight('bold')
                    ->summarize(Sum::make()->label('Total')->numeric(thousandsSeparator: ' ')),

                TextColumn::make('paid')
                    ->label('Payé')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' XOF')
                    ->color('success')
                    ->toggleable(),

                TextColumn::make('balance')
                    ->label('Solde dû')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' XOF')
                    ->sortable()
                    ->color(fn ($state): string => (float) $state > 0 ? 'danger' : 'gray'),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),

                TextColumn::make('due_date')
                    ->label('Échéance')
                    ->date('d/m/Y')
                    ->color(fn (Sale $record): string => $record->isOverdue() ? 'danger' : 'gray')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('seller.name')
                    ->label('Vendeur')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(SaleStatus::class)
                    ->multiple(),

                Filter::make('overdue')
                    ->label('En retard')
                    ->query(fn ($query) => $query->overdue())
                    ->toggle(),

                Filter::make('unpaid')
                    ->label('Non soldées')
                    ->query(fn ($query) => $query->unpaid())
                    ->toggle(),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
