<?php

namespace App\Filament\Farm\Resources\Quotes\Pages;

use App\Enums\QuoteStatus;
use App\Filament\Farm\Resources\Quotes\QuoteResource;
use App\Models\Quote;
use App\Services\SaleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ViewQuote extends ViewRecord
{
    protected static string $resource = QuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadPdf')
                ->label('Télécharger PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (Quote $record): StreamedResponse {
                    $document = $record->load(['items', 'customer', 'seller', 'organisation']);

                    $pdf = Pdf::loadView('pdf.commercial-document', [
                        'type' => 'quote',
                        'document' => $document,
                    ])->setPaper('a4', 'portrait');

                    $filename = 'devis-'.$document->quote_number.'.pdf';

                    return response()->streamDownload(
                        fn () => print ($pdf->output()),
                        $filename,
                        ['Content-Type' => 'application/pdf'],
                    );
                }),

            Action::make('markAsSent')
                ->label('Marquer comme envoyé')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->visible(fn (Quote $record): bool => $record->isDraft() && QuoteResource::canPerformAction('update'))
                ->action(function (Quote $record): void {
                    $record->update(['status' => QuoteStatus::Sent]);
                    Notification::make()->title('Devis marqué comme envoyé.')->success()->send();
                }),

            Action::make('markAsAccepted')
                ->label('Marquer comme accepté')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (Quote $record): bool => in_array($record->status, [QuoteStatus::Draft, QuoteStatus::Sent], true) && QuoteResource::canPerformAction('update'))
                ->requiresConfirmation()
                ->action(function (Quote $record): void {
                    $record->update(['status' => QuoteStatus::Accepted]);
                    Notification::make()->title('Devis accepté.')->success()->send();
                }),

            Action::make('markAsRejected')
                ->label('Marquer comme refusé')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (Quote $record): bool => in_array($record->status, [QuoteStatus::Draft, QuoteStatus::Sent], true) && QuoteResource::canPerformAction('update'))
                ->requiresConfirmation()
                ->modalDescription('Le client a refusé le devis ? Cette action ne peut pas être annulée facilement.')
                ->action(function (Quote $record): void {
                    $record->update(['status' => QuoteStatus::Rejected]);
                    Notification::make()->title('Devis marqué comme refusé.')->warning()->send();
                }),

            Action::make('markAsExpired')
                ->label('Marquer comme expiré')
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->visible(fn (Quote $record): bool => $record->isExpired() && QuoteResource::canPerformAction('update'))
                ->requiresConfirmation()
                ->action(function (Quote $record): void {
                    $record->update(['status' => QuoteStatus::Expired]);
                    Notification::make()->title('Devis marqué comme expiré.')->send();
                }),

            Action::make('convertToSale')
                ->label('Convertir en facture')
                ->icon('heroicon-o-arrow-right-circle')
                ->color('primary')
                ->visible(fn (Quote $record): bool => $record->canBeConvertedToSale() && QuoteResource::canPerformAction('convert'))
                ->requiresConfirmation()
                ->modalDescription('Crée une facture à partir de ce devis et reprend toutes les lignes.')
                ->schema([
                    DatePicker::make('due_date')
                        ->label('Date d\'échéance (optionnelle)')
                        ->native(false)
                        ->helperText('Laisser vide pour une facture comptant'),
                ])
                ->action(function (Quote $record, array $data): void {
                    try {
                        $sale = app(SaleService::class)->convertQuoteToSale(
                            quote: $record,
                            dueDate: ! empty($data['due_date']) ? \Carbon\Carbon::parse($data['due_date']) : null,
                        );

                        Notification::make()
                            ->title('Devis converti en facture')
                            ->body("Facture {$sale->invoice_number} créée.")
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Conversion impossible')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            EditAction::make()
                ->visible(fn (Quote $record): bool => ! $record->isConverted()),
        ];
    }
}
