<?php

namespace App\Filament\Farm\Resources\AccountTransfers\Pages;

use App\Filament\Farm\Resources\AccountTransfers\AccountTransferResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAccountTransfers extends ListRecords
{
    protected static string $resource = AccountTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
