<?php

namespace App\Filament\Farm\Resources\JournalEntries\Pages;

use App\Exceptions\AccountingException;
use App\Filament\Farm\Resources\FiscalYears\FiscalYearResource;
use App\Filament\Farm\Resources\JournalEntries\JournalEntryResource;
use App\Models\ChartAccount;
use App\Models\FiscalYear;
use App\Services\AccountingService;
use App\Services\LedgerService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListJournalEntries extends ListRecords
{
    protected static string $resource = JournalEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->openingBalanceAction(),
            Action::make('verifyLedger')
                ->label('Vérifier l\'intégrité')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color('gray')
                ->action(function (LedgerService $ledger): void {
                    $result = $ledger->verify((int) Filament::getTenant()->getKey());

                    if ($result['valid']) {
                        Notification::make()
                            ->title('Ledger intègre')
                            ->body(sprintf('%d écriture(s) vérifiée(s) : aucune altération détectée.', $result['checked']))
                            ->success()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Intégrité du ledger compromise')
                        ->body(sprintf('Rupture à partir de l\'écriture %s (%s). Prévenez l\'administrateur : la base doit être rapprochée des ancrages.', $result['broken_entry_number'], $result['reason']))
                        ->danger()
                        ->persistent()
                        ->send();
                }),
        ];
    }

    /**
     * Reprise des soldes antérieurs (capital, immobilisations, emprunts, créances, dettes) au premier
     * jour de l'exercice repris. Une seule fois par organisation ; disparaît ensuite.
     */
    private function openingBalanceAction(): Action
    {
        $organisationId = fn (): int => (int) Filament::getTenant()->getKey();

        return Action::make('openingBalance')
            ->label('Bilan d\'ouverture')
            ->icon(Heroicon::OutlinedScale)
            ->color('warning')
            ->visible(fn (): bool => FiscalYearResource::canPerformAction('opening_balance')
                && app(AccountingService::class)->openingBalanceEntry($organisationId()) === null)
            ->modalHeading('Bilan d\'ouverture')
            ->modalDescription('Soldes au premier jour de l\'exercice repris, avant toute opération saisie dans GESTAVI. Comptes de bilan des classes 1 à 4 uniquement : la trésorerie vient du solde initial de chaque compte, les charges et produits des opérations de l\'exercice. Saisie unique et intangible : total débit = total crédit.')
            ->modalSubmitActionLabel('Enregistrer le bilan d\'ouverture')
            ->schema([
                DatePicker::make('date')
                    ->label('Date d\'ouverture')
                    ->required()
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default(fn (): string => FiscalYear::query()
                        ->where('organisation_id', $organisationId())
                        ->open()
                        ->orderBy('starts_on')
                        ->first()?->starts_on?->toDateString() ?? now()->startOfYear()->toDateString()),
                Repeater::make('lines')
                    ->label('Soldes')
                    ->minItems(2)
                    ->defaultItems(2)
                    ->addActionLabel('Ajouter un compte')
                    ->columns(4)
                    ->schema([
                        Select::make('code')
                            ->label('Compte')
                            ->required()
                            ->searchable()
                            ->columnSpan(2)
                            ->options(fn (): array => ChartAccount::query()
                                ->availableTo($organisationId())
                                ->where('is_active', true)
                                ->where('class', '<=', 4)
                                ->orderBy('code')
                                ->get()
                                ->mapWithKeys(fn (ChartAccount $account): array => [$account->code => $account->code.' — '.$account->label])
                                ->all()),
                        TextInput::make('debit')->label('Débit')->numeric()->minValue(0)->default(0)->suffix('XOF'),
                        TextInput::make('credit')->label('Crédit')->numeric()->minValue(0)->default(0)->suffix('XOF'),
                    ]),
            ])
            ->action(function (array $data) use ($organisationId): void {
                try {
                    $entry = app(AccountingService::class)->recordOpeningBalance(
                        $organisationId(),
                        $data['date'],
                        array_values($data['lines']),
                        Filament::auth()->id(),
                    );

                    Notification::make()
                        ->title('Bilan d\'ouverture enregistré')
                        ->body('Écriture '.$entry->entry_number.' au journal AN.')
                        ->success()
                        ->send();
                } catch (AccountingException $e) {
                    Notification::make()->title('Bilan d\'ouverture refusé')->body($e->getMessage())->danger()->persistent()->send();
                }
            });
    }
}
