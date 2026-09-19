<?php

namespace App\Filament\Farm\Resources\AccountTransfers\Pages;

use App\Filament\Farm\Resources\AccountTransfers\AccountTransferResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAccountTransfer extends EditRecord
{
    protected static string $resource = AccountTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
