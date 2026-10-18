<?php

namespace App\Filament\Farm\Resources\BankReconciliations\Pages;

use App\Exceptions\BankReconciliationException;
use App\Filament\Farm\Resources\BankReconciliations\BankReconciliationResource;
use App\Models\BankReconciliation;
use App\Services\BankReconciliationService;
use App\Services\Imports\UnsupportedSpreadsheetException;
use App\Services\Imports\UploadedImportFile;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

/**
 * Fiche d'un rapprochement : relevé et synthèse (solde pointé, écart), import du relevé,
 * correspondance automatique, clôture ; les transactions à pointer et les lignes du relevé sont
 * gérées par les relation managers, qui signalent chaque changement par REFRESH_EVENT.
 */
class ViewBankReconciliation extends ViewRecord
{
    protected static string $resource = BankReconciliationResource::class;

    /** @var array<string, mixed>|null */
    private ?array $summaryCache = null;

    #[On(BankReconciliationResource::REFRESH_EVENT)]
    public function refreshReconciliation(): void
    {
        $this->record->refresh();
        $this->summaryCache = null;
    }

    /**
     * Synthèse calculée une fois par rendu.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return $this->summaryCache ??= app(BankReconciliationService::class)->summary($this->getRecord());
    }

    protected function getHeaderActions(): array
    {
        $draft = fn (BankReconciliation $record): bool => $record->isDraft() && BankReconciliationResource::canPerformAction('update');

        return [
            Action::make('importStatement')
                ->label('Importer un relevé')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->visible($draft)
                ->modalHeading('Importer les lignes du relevé')
                ->modalDescription('Fichier Excel (.xlsx) ou CSV avec une colonne Date, des colonnes Débit et Crédit (ou un Montant signé), un Libellé et éventuellement une Référence. Les lignes postérieures à la date du relevé et celles déjà importées sont écartées : vous pouvez réimporter un relevé complété.')
                ->modalSubmitActionLabel('Importer')
                ->schema([
                    FileUpload::make('file')
                        ->label('Relevé')
                        ->disk(UploadedImportFile::DISK)
                        ->directory(UploadedImportFile::DIRECTORY)
                        // Seul un fichier envoyé dans ce formulaire est accepté, jamais un chemin saisi
                        ->preventFilePathTampering()
                        ->visibility('private')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                            'text/plain',
                        ])
                        ->maxSize(5120)
                        ->storeFileNamesIn('file_name')
                        ->required(),
                ])
                ->action(function (BankReconciliation $record, array $data): void {
                    try {
                        $upload = UploadedImportFile::fromState($data['file'] ?? null);
                    } catch (UnsupportedSpreadsheetException $e) {
                        $this->failure('Import impossible', $e->getMessage());

                        return;
                    }

                    $fileName = is_string($data['file_name'] ?? null) ? $data['file_name'] : basename($upload->relativePath);

                    try {
                        $result = app(BankReconciliationService::class)->importStatement($record, $upload->absolutePath(), $fileName);
                    } catch (BankReconciliationException|UnsupportedSpreadsheetException $e) {
                        $this->failure('Import impossible', $e->getMessage());

                        return;
                    } catch (\Throwable $e) {
                        report($e);
                        $this->failure('Import impossible', 'Le fichier n\'a pas pu être lu. Vérifiez son format (.xlsx ou .csv) et ses en-têtes.');

                        return;
                    } finally {
                        $upload->delete();
                    }

                    $body = sprintf('%d ligne(s) importée(s), %d doublon(s) ignoré(s).', $result['imported'], $result['duplicates']);

                    if ($result['after_statement'] > 0) {
                        $body .= sprintf(' %d ligne(s) postérieure(s) au relevé écartée(s).', $result['after_statement']);
                    }

                    if ($result['errors'] !== []) {
                        $body .= ' Lignes non lues : '.implode(' ', array_slice($result['errors'], 0, 5));
                    }

                    Notification::make()
                        ->title('Relevé importé')
                        ->body($body)
                        ->status($result['errors'] === [] ? 'success' : 'warning')
                        ->send();

                    $this->dispatch(BankReconciliationResource::REFRESH_EVENT);
                }),

            Action::make('autoMatch')
                ->label('Rapprocher automatiquement')
                ->icon('heroicon-o-sparkles')
                ->color('info')
                ->visible(fn (BankReconciliation $record): bool => $draft($record) && $this->summary()['lines_unmatched'] > 0)
                ->requiresConfirmation()
                ->modalDescription('Chaque ligne du relevé encore à rapprocher est associée à une transaction non pointée de même sens et de même montant, datée à ± '.BankReconciliationService::DEFAULT_DAY_TOLERANCE.' jours (la référence commune puis la date la plus proche départagent). Les transactions associées sont pointées.')
                ->action(function (BankReconciliation $record): void {
                    try {
                        $count = app(BankReconciliationService::class)->autoMatch($record);
                    } catch (BankReconciliationException $e) {
                        $this->failure('Rapprochement impossible', $e->getMessage());

                        return;
                    }

                    Notification::make()
                        ->title($count > 0 ? "{$count} ligne(s) rapprochée(s)" : 'Aucune correspondance trouvée')
                        ->body($count > 0 ? null : 'Associez les lignes restantes manuellement ou pointez directement les transactions.')
                        ->status($count > 0 ? 'success' : 'info')
                        ->send();

                    $this->dispatch(BankReconciliationResource::REFRESH_EVENT);
                }),

            Action::make('complete')
                ->label('Clôturer')
                ->icon('heroicon-o-lock-closed')
                ->color('success')
                ->visible(fn (BankReconciliation $record): bool => $record->isDraft() && BankReconciliationResource::canPerformAction('complete'))
                ->requiresConfirmation()
                ->modalHeading('Clôturer le rapprochement')
                ->modalDescription(function (): string {
                    $summary = $this->summary();

                    return sprintf(
                        'Solde du relevé : %s. Solde pointé : %s. Écart : %s. %s',
                        BankReconciliationService::money($summary['statement_balance']),
                        BankReconciliationService::money($summary['reconciled_balance']),
                        BankReconciliationService::money($summary['difference']),
                        $summary['is_balanced']
                            ? 'Une fois clôturé, le rapprochement ne se modifie plus et ses transactions restent pointées.'
                            : 'La clôture sera refusée tant que l\'écart n\'est pas nul.',
                    );
                })
                ->action(function (BankReconciliation $record): void {
                    try {
                        app(BankReconciliationService::class)->complete($record, Filament::auth()->id());
                    } catch (BankReconciliationException $e) {
                        $this->failure('Clôture refusée', $e->getMessage());

                        return;
                    }

                    $this->refreshReconciliation();
                    $this->dispatch(BankReconciliationResource::REFRESH_EVENT);

                    Notification::make()
                        ->title('Rapprochement clôturé')
                        ->success()
                        ->send();
                }),

            EditAction::make(),
            DeleteAction::make()
                ->successRedirectUrl(fn (): string => BankReconciliationResource::getUrl('index')),
        ];
    }

    private function failure(string $title, string $body): void
    {
        Notification::make()
            ->title($title)
            ->body($body)
            ->danger()
            ->persistent()
            ->send();
    }
}
