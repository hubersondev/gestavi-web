<?php

namespace App\Filament\Farm\Resources\Quotes\Tables;

use App\Enums\QuoteStatus;
use App\Services\CurrentFiscalYear;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class QuotesTable
{
    public static function configure(Table $table): Table
    {
        return app(CurrentFiscalYear::class)->applyToTable($table, 'quote_date')
            ->defaultSort('quote_date', 'desc')
            ->columns([
                TextColumn::make('quote_number')
                    ->label('N°')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->weight('bold'),

                TextColumn::make('quote_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('valid_until')
                    ->label('Valide jusqu\'au')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('customer.name')
                    ->label('Client')
                    ->searchable()
                    ->placeholder(fn ($record): string => $record->client_name ?? '—'),

                TextColumn::make('total')
                    ->label('Total')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' XOF')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),

                TextColumn::make('converted_sale.invoice_number')
                    ->label('Facture')
                    ->badge()
                    ->color('success')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(QuoteStatus::class)
                    ->multiple(),

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
