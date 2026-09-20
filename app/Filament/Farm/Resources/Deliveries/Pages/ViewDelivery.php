<?php

namespace App\Filament\Farm\Resources\Deliveries\Pages;

use App\Filament\Farm\Resources\Deliveries\DeliveryResource;
use App\Filament\Farm\Resources\Deliveries\Tables\DeliveriesTable;
use App\Models\Delivery;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewDelivery extends ViewRecord
{
    protected static string $resource = DeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadPdf')
                ->label('Bon de livraison PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (Delivery $record): StreamedResponse {
                    $delivery = $record->load(['sale.items', 'sale.customer', 'sale.organisation', 'farm']);

                    $pdf = Pdf::loadView('pdf.delivery-note', ['delivery' => $delivery])->setPaper('a4', 'portrait');

                    return response()->streamDownload(
                        fn () => print ($pdf->output()),
                        'bon-livraison-'.$delivery->delivery_number.'.pdf',
                        ['Content-Type' => 'application/pdf'],
                    );
                }),

            Action::make('start_transit')
                ->label('Départ en livraison')
                ->icon('heroicon-o-truck')
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn (Delivery $record): bool => $record->status === \App\Enums\DeliveryStatus::Planned && DeliveryResource::canPerformAction('update'))
                ->action(function (Delivery $record): void {
                    $record->markInTransit();
                    Notification::make()->title('Livraison en cours.')->info()->send();
                    $this->refreshFormData(['status']);
                }),

            DeliveriesTable::markDeliveredAction(),

            Action::make('mark_failed')
                ->label('Échec de livraison')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (Delivery $record): bool => $record->isOpen() && DeliveryResource::canPerformAction('update'))
                ->form([
                    \Filament\Forms\Components\Textarea::make('reason')->label('Motif')->required()->rows(2),
                ])
                ->action(function (Delivery $record, array $data): void {
                    $record->markFailed($data['reason']);
                    Notification::make()->title('Livraison marquée en échec.')->warning()->send();
                    $this->refreshFormData(['status', 'notes']);
                }),

            EditAction::make()->visible(fn () => DeliveryResource::canEdit($this->record)),
        ];
    }
}
