<?php

namespace App\Filament\Farm\Resources\BankReconciliations\Pages;

use App\Exceptions\BankReconciliationException;
use App\Filament\Farm\Resources\Accounts\AccountResource;
use App\Filament\Farm\Resources\BankReconciliations\BankReconciliationResource;
use App\Models\Account;
use App\Services\BankReconciliationService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBankReconciliation extends CreateRecord
{
    protected static string $resource = BankReconciliationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var Account $account */
        $account = AccountResource::getEloquentQuery()->findOrFail($data['account_id']);

        try {
            return app(BankReconciliationService::class)->start(
                $account,
                $data['statement_date'],
                (float) $data['statement_balance'],
                $data['notes'] ?? null,
                Filament::auth()->id(),
            );
        } catch (BankReconciliationException $e) {
            Notification::make()
                ->title('Ouverture impossible')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
