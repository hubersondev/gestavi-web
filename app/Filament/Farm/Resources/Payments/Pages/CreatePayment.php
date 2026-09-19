<?php

namespace App\Filament\Farm\Resources\Payments\Pages;

use App\Enums\PaymentMethod;
use App\Filament\Farm\Resources\Payments\PaymentResource;
use App\Models\Sale;
use App\Services\SaleService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            /** @var Sale $sale */
            $sale = Sale::findOrFail($data['sale_id']);

            $payment = app(SaleService::class)->recordPayment(
                sale: $sale,
                amount: (float) $data['amount'],
                method: PaymentMethod::from($data['method']),
                accountId: $data['account_id'] ?? null,
                reference: $data['reference'] ?? null,
                paidAt: \Carbon\Carbon::parse($data['payment_date']),
            );

            if (! empty($data['notes'])) {
                $payment->update(['notes' => $data['notes']]);
            }

            return $payment;
        } catch (Throwable $e) {
            Notification::make()
                ->title('Impossible d\'enregistrer le paiement')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }
    }
}
