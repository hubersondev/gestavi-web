<?php

namespace App\Filament\Farm\Resources\StockInventories\Pages;

use App\Enums\StockInventoryStatus;
use App\Filament\Farm\Resources\StockInventories\StockInventoryResource;
use App\Models\StockInventory;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ViewStockInventory extends ViewRecord
{
    protected static string $resource = StockInventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadPdf')
                ->label('Télécharger PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (StockInventory $record): StreamedResponse {
                    $inventory = $record->load(['stock.category', 'stockLot', 'conductor', 'validator', 'organisation']);

                    $pdf = Pdf::loadView('pdf.stock-inventory', [
                        'inventory' => $inventory,
                    ])->setPaper('a4', 'portrait');

                    $filename = 'inventaire-'.$inventory->inventory_ref.'.pdf';

                    return response()->streamDownload(
                        fn () => print ($pdf->output()),
                        $filename,
                        ['Content-Type' => 'application/pdf'],
                    );
                }),

            Action::make('validate')
                ->label('Valider l\'inventaire')
                ->icon('heroicon-o-check-badge')
                ->color('info')
                ->visible(fn (StockInventory $record): bool => $record->isDraft() && StockInventoryResource::canPerformAction('validate'))
                ->requiresConfirmation()
                ->action(function (StockInventory $record): void {
                    $record->update([
                        'status' => StockInventoryStatus::Validated,
                        'validated_by' => auth()->id(),
                        'validated_at' => now(),
                    ]);
                    Notification::make()->title('Inventaire validé.')->success()->send();
                }),

            Action::make('applyAdjustment')
                ->label('Ajuster le stock')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (StockInventory $record): bool => $record->canBeAdjusted() && StockInventoryResource::canPerformAction('adjust'))
                ->requiresConfirmation()
                ->modalDescription(fn (StockInventory $record): string => $record->hasDifference()
                    ? sprintf(
                        'Créer un mouvement d\'ajustement de %s %s sur le stock "%s" ?',
                        ($record->isPositiveDifference() ? '+' : '').rtrim(rtrim(number_format((float) $record->difference, 2, ',', ' '), '0'), ','),
                        $record->stock?->unit ?? '',
                        $record->stock?->name ?? '—',
                    )
                    : 'Pas d\'écart détecté — l\'inventaire sera simplement marqué comme ajusté.')
                ->action(function (StockInventory $record): void {
                    try {
                        $record->applyAdjustment();
                        Notification::make()->title('Ajustement appliqué au stock.')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Ajustement impossible')->body($e->getMessage())->danger()->send();
                    }
                }),

            EditAction::make()
                ->visible(fn (StockInventory $record): bool => $record->isDraft()),
        ];
    }
}
