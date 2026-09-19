<?php

namespace App\Filament\Farm\Resources\PurchaseOrders\Pages;

use App\Enums\PurchaseOrderStatus;
use App\Filament\Farm\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Services\StockService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ViewPurchaseOrder extends ViewRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadPdf')
                ->label('Télécharger PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (PurchaseOrder $record): StreamedResponse {
                    $order = $record->load(['items', 'supplier', 'farm', 'organisation', 'creator']);

                    $pdf = Pdf::loadView('pdf.purchase-order', [
                        'order' => $order,
                    ])->setPaper('a4', 'portrait');

                    $filename = 'bc-'.$order->order_number.'.pdf';

                    return response()->streamDownload(
                        fn () => print ($pdf->output()),
                        $filename,
                        ['Content-Type' => 'application/pdf'],
                    );
                }),

            Action::make('approve')
                ->label('Approuver')
                ->icon('heroicon-o-check-badge')
                ->color('info')
                ->visible(fn (PurchaseOrder $record): bool => $record->canBeApproved() && PurchaseOrderResource::canPerformAction('approve'))
                ->requiresConfirmation()
                ->action(function (PurchaseOrder $record): void {
                    $record->update([
                        'status' => PurchaseOrderStatus::Approved,
                        'approved_by' => auth()->id(),
                    ]);
                    Notification::make()->title('BC approuvé.')->success()->send();
                }),

            Action::make('send')
                ->label('Envoyer au fournisseur')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->visible(fn (PurchaseOrder $record): bool => $record->status === PurchaseOrderStatus::Approved && PurchaseOrderResource::canPerformAction('send'))
                ->requiresConfirmation()
                ->action(function (PurchaseOrder $record): void {
                    try {
                        app(StockService::class)->sendPurchaseOrder($record);
                        Notification::make()->title('BC marqué comme envoyé.')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Erreur')->body($e->getMessage())->danger()->send();
                    }
                }),

            Action::make('receive')
                ->label('Réceptionner')
                ->icon('heroicon-o-truck')
                ->color('success')
                ->visible(fn (PurchaseOrder $record): bool => $record->canBeReceived() && PurchaseOrderResource::canPerformAction('receive'))
                ->schema([
                    CheckboxList::make('all_items')
                        ->label('Tout réceptionner')
                        ->options(fn (PurchaseOrder $record): array => $record->items
                            ->mapWithKeys(fn ($item) => [$item->id => "{$item->product_name} — reste ".rtrim(rtrim(number_format($item->getQuantityPending(), 2, ',', ' '), '0'), ',').' '.$item->unit])
                            ->toArray())
                        ->bulkToggleable()
                        ->columns(1)
                        ->dehydrated(false),
                ])
                ->action(function (PurchaseOrder $record, array $data): void {
                    try {
                        $itemIds = $data['all_items'] ?? [];
                        $receptions = [];

                        foreach ($itemIds as $itemId) {
                            $item = $record->items()->find($itemId);
                            if ($item) {
                                $receptions[] = [
                                    'item_id' => (int) $itemId,
                                    'quantity' => $item->getQuantityPending(),
                                ];
                            }
                        }

                        if (empty($receptions)) {
                            Notification::make()->title('Aucune ligne sélectionnée')->warning()->send();

                            return;
                        }

                        app(StockService::class)->receivePurchaseOrder($record, $receptions);
                        Notification::make()->title('Réception enregistrée.')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Réception impossible')->body($e->getMessage())->danger()->send();
                    }
                }),

            Action::make('cancel')
                ->label('Annuler')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (PurchaseOrder $record): bool => $record->canBeCancelled() && PurchaseOrderResource::canPerformAction('delete'))
                ->requiresConfirmation()
                ->action(function (PurchaseOrder $record): void {
                    try {
                        app(StockService::class)->cancelPurchaseOrder($record);
                        Notification::make()->title('BC annulé.')->warning()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Annulation impossible')->body($e->getMessage())->danger()->send();
                    }
                }),

            EditAction::make()
                ->visible(fn (PurchaseOrder $record): bool => $record->isDraft()),
        ];
    }
}
