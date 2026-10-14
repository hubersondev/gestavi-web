<?php

namespace App\Filament\Farm\Resources\CreditNotes\Tables;

use App\Enums\CreditNoteStatus;
use App\Services\CurrentFiscalYear;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CreditNotesTable
{
    public static function configure(Table $table): Table
    {
        return app(CurrentFiscalYear::class)->applyToTable($table, 'issue_date')
            ->defaultSort('issue_date', 'desc')
            ->columns([
                TextColumn::make('credit_note_number')
                    ->label('N°')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('warning')
                    ->weight('bold'),

                TextColumn::make('issue_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('sale.invoice_number')
                    ->label('Facture')
                    ->badge()
                    ->color('primary')
                    ->searchable(),

                TextColumn::make('customer.name')
                    ->label('Client')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('amount')
                    ->label('Montant')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' XOF')
                    ->sortable()
                    ->weight('bold')
                    ->summarize(Sum::make()->label('Total')->numeric(thousandsSeparator: ' ')),

                TextColumn::make('reason')
                    ->label('Motif')
                    ->limit(30)
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),

                TextColumn::make('issuer.name')
                    ->label('Émis par')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(CreditNoteStatus::class)
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
