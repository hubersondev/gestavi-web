<?php

namespace App\Filament\Farm\Resources\MobileMoneyTransactions\Pages;

use App\Filament\Farm\Resources\MobileMoneyTransactions\MobileMoneyTransactionResource;
use App\Filament\Farm\Resources\MobileMoneyTransactions\Support\ReconciliationActions;
use Filament\Resources\Pages\ViewRecord;

class ViewMobileMoneyTransaction extends ViewRecord
{
    protected static string $resource = MobileMoneyTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ReconciliationActions::match(),
            ReconciliationActions::ignore(),
        ];
    }
}
