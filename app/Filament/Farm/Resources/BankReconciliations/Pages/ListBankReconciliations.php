<?php

namespace App\Filament\Farm\Resources\BankReconciliations\Pages;

use App\Filament\Farm\Resources\BankReconciliations\BankReconciliationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBankReconciliations extends ListRecords
{
    protected static string $resource = BankReconciliationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nouveau rapprochement'),
        ];
    }
}
