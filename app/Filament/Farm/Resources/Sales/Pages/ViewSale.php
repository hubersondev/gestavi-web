<?php

namespace App\Filament\Farm\Resources\Sales\Pages;

use App\Enums\PaymentMethod;
use App\Filament\Farm\Resources\Sales\SaleResource;
use App\Models\Account;
use App\Models\Sale;
use App\Services\SaleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ViewSale extends ViewRecord
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadPdf')
                ->label('Télécharger PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (Sale $record): StreamedResponse {
                    $document = $record->load(['items', 'customer', 'seller', 'organisation']);

                    $pdf = Pdf::loadView('pdf.commercial-document', [
                        'type' => 'sale',
                        'document' => $document,
                    ])->setPaper('a4', 'portrait');

                    $filename = 'facture-'.$document->invoice_number.'.pdf';

                    return response()->streamDownload(
                        fn () => print ($pdf->output()),
                        $filename,
                        ['Content-Type' => 'application/pdf'],
                    );
                }),

            Action::make('recordPayment')
                ->label('Enregistrer un paiement')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn (Sale $record): bool => $record->hasBalance() && ! $record->isCancelled() && SaleResource::canPerformAction('record_payment'))
                ->schema([
                    TextInput::make('amount')
                        ->label('Montant')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->suffix('XOF')
                        ->default(fn (Sale $record) => (float) $record->balance),

                    Select::make('method')
                        ->label('Mode de paiement')
                        ->options(PaymentMethod::class)
                        ->required()
                        ->native(false),

                    Select::make('account_id')
                        ->label('Compte d\'encaissement')
                        ->options(function (): array {
                            $tenant = Filament::getTenant();

                            return Account::query()
                                ->where('organisation_id', $tenant?->getKey())
                                ->active()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->searchable()
                        ->nullable()
                        ->helperText('Optionnel — crédite automatiquement le compte'),

                    DatePicker::make('payment_date')
                        ->label('Date du paiement')
                        ->default(now())
                        ->native(false)
                        ->required(),

                    TextInput::make('reference')
                        ->label('Référence')
                        ->maxLength(255)
                        ->helperText('N° transaction mobile money, chèque…'),
                ])
                ->action(function (Sale $record, array $data): void {
                    try {
                        app(SaleService::class)->recordPayment(
                            sale: $record,
                            amount: (float) $data['amount'],
                            method: PaymentMethod::from($data['method']),
                            accountId: $data['account_id'] ?? null,
                            reference: $data['reference'] ?? null,
                            paidAt: \Carbon\Carbon::parse($data['payment_date']),
                        );

                        Notification::make()
                            ->title('Paiement enregistré')
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Paiement impossible')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('createDelivery')
                ->label('Créer une livraison')
                ->icon('heroicon-o-truck')
                ->color('info')
                ->visible(fn (Sale $record): bool => ! $record->isCancelled()
                    && \App\Filament\Farm\Resources\Deliveries\DeliveryResource::canCreate()
                    && Filament::getTenant()?->hasFeature(\App\Enums\PlanFeature::Deliveries))
                ->url(fn (Sale $record): string => \App\Filament\Farm\Resources\Deliveries\DeliveryResource::getUrl('create', ['sale' => $record->getKey()])),

            Action::make('cancel')
                ->label('Annuler la facture')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (Sale $record): bool => ! $record->isCancelled() && SaleResource::canPerformAction('cancel'))
                ->requiresConfirmation()
                ->modalDescription('Annule la facture et contrepasse tous les paiements comptables liés.')
                ->action(function (Sale $record): void {
                    try {
                        app(SaleService::class)->cancelSale($record);
                        Notification::make()->title('Facture annulée.')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Annulation impossible')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            EditAction::make(),
        ];
    }
}
