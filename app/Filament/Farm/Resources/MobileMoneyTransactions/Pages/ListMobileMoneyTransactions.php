<?php

namespace App\Filament\Farm\Resources\MobileMoneyTransactions\Pages;

use App\Filament\Farm\Resources\MobileMoneyTransactions\MobileMoneyTransactionResource;
use Filament\Resources\Pages\ListRecords;

class ListMobileMoneyTransactions extends ListRecords
{
    protected static string $resource = MobileMoneyTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
