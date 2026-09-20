<?php

namespace App\Filament\Farm\Resources\Payments\Pages;

use App\Filament\Farm\Resources\MobileMoneyTransactions\MobileMoneyTransactionResource;
use App\Filament\Farm\Resources\Payments\PaymentResource;
use App\Models\MobileMoneyTransaction;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('mobileMoneyTransaction')
                ->label('Encaissement mobile money')
                ->icon('heroicon-o-device-phone-mobile')
                ->color('gray')
                ->visible(fn (): bool => $this->matchedTransaction() !== null && MobileMoneyTransactionResource::canViewAny())
                ->url(fn (): ?string => ($transaction = $this->matchedTransaction())
                    ? MobileMoneyTransactionResource::getUrl('view', ['record' => $transaction])
                    : null),
        ];
    }

    /**
     * Transaction mobile money rapprochée avec ce paiement, s'il y en a une.
     */
    protected function matchedTransaction(): ?MobileMoneyTransaction
    {
        return MobileMoneyTransaction::query()->where('payment_id', $this->record->getKey())->first();
    }
}
