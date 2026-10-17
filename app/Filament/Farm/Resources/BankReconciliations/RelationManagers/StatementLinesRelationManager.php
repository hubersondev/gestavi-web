<?php

namespace App\Filament\Farm\Resources\BankReconciliations\RelationManagers;

use App\Enums\BankStatementLineStatus;
use App\Exceptions\BankReconciliationException;
use App\Filament\Farm\Resources\BankReconciliations\BankReconciliationResource;
use App\Models\BankReconciliation;
use App\Models\BankStatementLine;
use App\Models\Transaction;
use App\Services\BankReconciliationService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Livewire\Attributes\On;

/**
 * Lignes du relevé importé : association manuelle à une transaction (propositions de même montant),
 * dissociation, mise à l'écart des lignes sans objet.
 */
class StatementLinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Lignes du relevé';

    protected static ?string $recordTitleAttribute = 'label';

    #[On(BankReconciliationResource::REFRESH_EVENT)]
    public function refreshAfterReconciliation(): void
    {
        $this->getOwnerRecord()->refresh();
    }

    public function table(Table $table): Table
    {
        $draft = fn (): bool => $this->reconciliation()->isDraft() && BankReconciliationResource::canPerformAction('update');

        return $table
            ->description('Lignes du relevé importé. Chaque ligne associée à une transaction la pointe ; ignorez celles qui n\'ont pas d\'objet (doublon, frais déjà saisis autrement).')
            ->emptyStateHeading('Aucune ligne importée')
            ->emptyStateDescription('Importez le relevé avec le bouton « Importer un relevé » en haut de page, ou pointez directement les transactions.')
            ->defaultSort('line_date')
            ->modifyQueryUsing(fn ($query) => $query->with('matchedTransaction'))
            ->columns([
                TextColumn::make('line_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('label')
                    ->label('Libellé')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(fn (BankStatementLine $record): string => $record->label),

                TextColumn::make('amount')
                    ->label('Montant')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' XOF')
                    ->sortable()
                    ->weight('bold')
                    ->color(fn (BankStatementLine $record): string => (float) $record->amount >= 0 ? 'success' : 'danger'),

                TextColumn::make('reference')
                    ->label('Référence')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),

                TextColumn::make('matchedTransaction.description')
                    ->label('Transaction associée')
                    ->limit(40)
                    ->placeholder('—')
                    ->description(fn (BankStatementLine $record): ?string => $record->matchedTransaction
                        ? $record->matchedTransaction->transaction_date->format('d/m/Y').' · #'.str_pad((string) $record->matchedTransaction->id, 8, '0', STR_PAD_LEFT)
                        : null),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(BankStatementLineStatus::class),
            ])
            ->recordActions([
                Action::make('match')
                    ->label('Associer')
                    ->icon('heroicon-o-link')
                    ->color('success')
                    ->visible(fn (BankStatementLine $record): bool => $record->isUnmatched() && $draft())
                    ->modalHeading(fn (BankStatementLine $record): string => 'Associer la ligne du '.$record->line_date->format('d/m/Y'))
                    ->modalSubmitActionLabel('Associer et pointer')
                    ->schema(fn (BankStatementLine $record): array => [
                        Select::make('transaction_id')
                            ->label('Transaction')
                            ->options(fn (): array => app(BankReconciliationService::class)->suggestionsFor($record)
                                ->mapWithKeys(fn (Transaction $transaction): array => [
                                    $transaction->id => sprintf(
                                        '%s — %s XOF — %s',
                                        $transaction->transaction_date->format('d/m/Y'),
                                        number_format((float) $transaction->amount, 0, ',', ' '),
                                        (string) $transaction->description,
                                    ),
                                ])
                                ->all())
                            ->helperText('Transactions non pointées de même sens et de même montant, à ± 45 jours de la ligne.')
                            ->required()
                            ->searchable(),
                    ])
                    ->action(function (BankStatementLine $record, array $data): void {
                        $transaction = Transaction::query()->findOrFail($data['transaction_id']);

                        $this->run(fn () => app(BankReconciliationService::class)->matchLine($record, $transaction), 'Ligne associée et transaction pointée', 'Association impossible');
                    }),

                Action::make('unmatch')
                    ->label('Dissocier')
                    ->icon('heroicon-o-link-slash')
                    ->color('gray')
                    ->visible(fn (BankStatementLine $record): bool => $record->isMatched() && $draft())
                    ->requiresConfirmation()
                    ->modalDescription('La transaction associée redevient à pointer.')
                    ->action(fn (BankStatementLine $record) => $this->run(fn () => app(BankReconciliationService::class)->unmatchLine($record), 'Ligne dissociée', 'Dissociation impossible')),

                Action::make('ignore')
                    ->label('Ignorer')
                    ->icon('heroicon-o-eye-slash')
                    ->color('gray')
                    ->visible(fn (BankStatementLine $record): bool => $record->isUnmatched() && $draft())
                    ->action(fn (BankStatementLine $record) => $this->run(fn () => app(BankReconciliationService::class)->ignoreLine($record), 'Ligne ignorée', 'Action impossible')),

                Action::make('restore')
                    ->label('Rétablir')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn (BankStatementLine $record): bool => $record->isIgnored() && $draft())
                    ->action(fn (BankStatementLine $record) => $this->run(fn () => app(BankReconciliationService::class)->restoreLine($record), 'Ligne rétablie', 'Action impossible')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Supprimer les lignes')
                        ->visible($draft)
                        ->after(fn () => $this->dispatch(BankReconciliationResource::REFRESH_EVENT)),
                ]),
            ]);
    }

    private function reconciliation(): BankReconciliation
    {
        /** @var BankReconciliation $record */
        $record = $this->getOwnerRecord();

        return $record;
    }

    private function run(\Closure $operation, string $successTitle, string $failureTitle): void
    {
        try {
            $operation();
        } catch (BankReconciliationException $e) {
            Notification::make()
                ->title($failureTitle)
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title($successTitle)
            ->success()
            ->send();

        $this->dispatch(BankReconciliationResource::REFRESH_EVENT);
    }
}
