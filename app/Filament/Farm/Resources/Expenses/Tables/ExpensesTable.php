<?php

namespace App\Filament\Farm\Resources\Expenses\Tables;

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseStatus;
use App\Models\Expense;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ExpensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('expense_date', 'desc')
            ->columns([
                TextColumn::make('expense_number')
                    ->label('N°')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('danger')
                    ->weight('bold'),

                TextColumn::make('expense_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('category')
                    ->label('Catégorie')
                    ->badge(),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn (Expense $record): string => $record->description),

                TextColumn::make('supplier.name')
                    ->label('Fournisseur')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('amount')
                    ->label('Montant')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' XOF')
                    ->sortable()
                    ->weight('bold')
                    ->summarize(Sum::make()->label('Total')->numeric(thousandsSeparator: ' ')),

                TextColumn::make('balance')
                    ->label('Solde dû')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' XOF')
                    ->color(fn ($state): string => (float) $state > 0 ? 'warning' : 'gray')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),

                TextColumn::make('due_date')
                    ->label('Échéance')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->color(fn (Expense $record): string => $record->isOverdue() ? 'danger' : 'gray')
                    ->toggleable(),

                TextColumn::make('farm.name')
                    ->label('Ferme')
                    ->placeholder('Organisation')
                    ->toggleable(),

                TextColumn::make('flock.code')
                    ->label('Bande')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('attachment')
                    ->label('Justif.')
                    ->boolean()
                    ->trueIcon('heroicon-o-paper-clip')
                    ->falseIcon('heroicon-o-minus')
                    ->getStateUsing(fn (Expense $record): bool => ! empty($record->attachment))
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options(ExpenseCategory::class)
                    ->multiple(),

                SelectFilter::make('status')
                    ->options(ExpenseStatus::class)
                    ->multiple(),

                Filter::make('unpaid')
                    ->label('Non soldées')
                    ->query(fn ($query) => $query->unpaid())
                    ->toggle(),

                Filter::make('overdue')
                    ->label('En retard')
                    ->query(fn ($query) => $query->overdue())
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
