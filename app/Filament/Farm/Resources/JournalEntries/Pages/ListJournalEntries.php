<?php

namespace App\Filament\Farm\Resources\JournalEntries\Pages;

use App\Filament\Farm\Resources\JournalEntries\JournalEntryResource;
use App\Services\LedgerService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListJournalEntries extends ListRecords
{
    protected static string $resource = JournalEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
}
