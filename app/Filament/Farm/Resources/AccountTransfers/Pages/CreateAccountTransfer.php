<?php

namespace App\Filament\Farm\Resources\AccountTransfers\Pages;

use App\Filament\Farm\Resources\AccountTransfers\AccountTransferResource;
use App\Services\AccountService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class CreateAccountTransfer extends CreateRecord
{
    protected static string $resource = AccountTransferResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(AccountService::class)->executeTransfer(
                organisationId: Filament::getTenant()?->getKey(),
                fromAccountId: (int) $data['from_account_id'],
                toAccountId: (int) $data['to_account_id'],
                amount: (float) $data['amount'],
                fees: (float) ($data['fees'] ?? 0),
                description: $data['description'] ?? null,
                notes: $data['notes'] ?? null,
            );
        } catch (Throwable $e) {
            Notification::make()
                ->title('Transfert impossible')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }
    }
}
