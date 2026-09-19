<?php

namespace App\Filament\Farm\Resources\PurchaseOrders\Pages;

use App\Enums\PurchaseOrderStatus;
use App\Filament\Farm\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Services\StockService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
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
                ->modalHeading('Réception ligne par ligne')
                ->modalDescription('Quantité reçue (0 = non reçue), numéro de lot fournisseur, péremption et prix réel par article. Un lot est créé dès qu\'un numéro de lot est saisi.')
                ->modalWidth('5xl')
                ->schema([
                    Repeater::make('receptions')
                        ->label('Articles à réceptionner')
                        ->default(fn (PurchaseOrder $record): array => $record->items
                            ->filter(fn ($item) => $item->getQuantityPending() > 0)
                            ->map(fn ($item): array => [
                                'item_id' => $item->id,
                                'product_name' => "{$item->product_name} ({$item->unit})",
                                'pending' => $item->getQuantityPending(),
                                'quantity' => $item->getQuantityPending(),
                                'lot_number' => null,
                                'expiry_date' => null,
                                'unit_price' => (float) $item->unit_price,
                            ])
                            ->values()
                            ->all())
                        ->schema([
                            Hidden::make('item_id'),
                            Hidden::make('pending'),

                            TextInput::make('product_name')
                                ->label('Article')
                                ->disabled()
                                ->dehydrated(false)
                                ->columnSpan(3),

                            TextInput::make('quantity')
                                ->label('Qté reçue')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->maxValue(fn (Get $get): float => (float) $get('pending'))
                                ->helperText(fn (Get $get): string => 'Reste : '.rtrim(rtrim(number_format((float) $get('pending'), 2, ',', ' '), '0'), ','))
                                ->columnSpan(2),

                            TextInput::make('lot_number')
                                ->label('N° lot fournisseur')
                                ->maxLength(255)
                                ->columnSpan(2),

                            DatePicker::make('expiry_date')
                                ->label('Péremption')
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->minDate(now())
                                ->columnSpan(2),

                            TextInput::make('unit_price')
                                ->label('Prix unitaire réel')
                                ->numeric()
                                ->minValue(0)
                                ->suffix('XOF')
                                ->columnSpan(2),
                        ])
                        ->columns(11)
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->columnSpanFull(),
                ])
                ->action(function (PurchaseOrder $record, array $data): void {
                    try {
                        $receptions = collect($data['receptions'] ?? [])
                            ->filter(fn (array $line): bool => (float) ($line['quantity'] ?? 0) > 0)
                            ->map(fn (array $line): array => [
                                'item_id' => (int) $line['item_id'],
                                'quantity' => (float) $line['quantity'],
                                'lot_number' => filled($line['lot_number'] ?? null) ? trim((string) $line['lot_number']) : null,
                                'expiry_date' => $line['expiry_date'] ?? null,
                                'unit_price' => filled($line['unit_price'] ?? null) ? (float) $line['unit_price'] : null,
                            ])
                            ->values()
                            ->all();

                        if (empty($receptions)) {
                            Notification::make()->title('Aucune quantité reçue saisie')->warning()->send();

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
