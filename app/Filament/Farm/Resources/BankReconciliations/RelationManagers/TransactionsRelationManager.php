<?php

namespace App\Filament\Farm\Resources\BankReconciliations\RelationManagers;

use App\Enums\TransactionType;
use App\Exceptions\BankReconciliationException;
use App\Filament\Farm\Resources\BankReconciliations\BankReconciliationResource;
use App\Models\BankReconciliation;
use App\Models\Transaction;
use App\Services\BankReconciliationService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;

/**
 * Transactions du compte à pointer : datées au plus tard du relevé et non rapprochées, plus
 * celles déjà pointées dans ce rapprochement. Pointage à l'unité ou en masse.
 */
class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'candidateTransactions';

    protected static ?string $title = 'Transactions à pointer';

    protected static ?string $recordTitleAttribute = 'description';

    /** Le nouveau rendu suffit : les lignes sont relues à chaque rendu. */
    #[On(BankReconciliationResource::REFRESH_EVENT)]
    public function refreshAfterReconciliation(): void {}

    public function table(Table $table): Table
    {
        $draft = fn (): bool => $this->reconciliation()->isDraft() && BankReconciliationResource::canPerformAction('update');

        return $table
            ->description('Mouvements du compte datés au plus tard du relevé : non pointés, pointés ici, ou pointés à l\'unité hors rapprochement (rattachés à celui-ci à la clôture). Pointez ceux qui figurent sur le relevé.')
            ->defaultSort('transaction_date', 'desc')
            ->columns([
                TextColumn::make('transaction_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->description(fn (Transaction $record): string => '#'.str_pad((string) $record->id, 8, '0', STR_PAD_LEFT)),

                TextColumn::make('type')
                    ->label('Sens')
                    ->badge(),

                TextColumn::make('amount')
                    ->label('Montant')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' XOF')
                    ->sortable()
                    ->weight('bold')
                    ->color(fn (Transaction $record): string => $record->type === TransactionType::Credit ? 'success' : 'danger'),

                TextColumn::make('category')
                    ->label('Catégorie')
                    ->badge(),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(45)
                    ->tooltip(fn (Transaction $record): ?string => $record->description),

                TextColumn::make('reference_number')
                    ->label('Référence')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),

                IconColumn::make('is_reconciled')
                    ->label('Pointée')
                    ->boolean()
                    ->tooltip(fn (Transaction $record): ?string => $record->is_reconciled && $record->bank_reconciliation_id === null ? 'Pointée hors rapprochement' : null),
            ])
            ->filters([
                TernaryFilter::make('is_reconciled')
                    ->label('Pointée')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where('transactions.is_reconciled', true),
                        false: fn (Builder $query): Builder => $query->where('transactions.is_reconciled', false),
                    ),
            ])
            ->recordActions([
                Action::make('reconcile')
                    ->label('Pointer')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Transaction $record): bool => ! $record->is_reconciled && $draft())
                    ->action(fn (Transaction $record) => $this->mark([$record->id], true)),

                Action::make('unreconcile')
                    ->label('Dépointer')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(fn (Transaction $record): bool => $record->is_reconciled && $draft())
                    ->action(fn (Transaction $record) => $this->mark([$record->id], false)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('reconcileSelected')
                        ->label('Pointer la sélection')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->visible($draft)
                        ->action(fn (Collection $records) => $this->mark($records->modelKeys(), true))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('unreconcileSelected')
                        ->label('Dépointer la sélection')
                        ->icon('heroicon-o-x-mark')
                        ->color('gray')
                        ->visible($draft)
                        ->action(fn (Collection $records) => $this->mark($records->modelKeys(), false))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    private function reconciliation(): BankReconciliation
    {
        /** @var BankReconciliation $record */
        $record = $this->getOwnerRecord();

        return $record;
    }

    /**
     * @param  iterable<int>  $ids
     */
    private function mark(iterable $ids, bool $reconciled): void
    {
        try {
            $count = app(BankReconciliationService::class)->mark($this->reconciliation(), $ids, $reconciled);
        } catch (BankReconciliationException $e) {
            Notification::make()
                ->title($reconciled ? 'Pointage impossible' : 'Dépointage impossible')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(sprintf('%d transaction(s) %s', $count, $reconciled ? 'pointée(s)' : 'dépointée(s)'))
            ->success()
            ->send();

        $this->dispatch(BankReconciliationResource::REFRESH_EVENT);
    }
}
