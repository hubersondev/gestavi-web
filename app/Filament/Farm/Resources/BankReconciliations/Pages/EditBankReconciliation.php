<?php

namespace App\Filament\Farm\Resources\BankReconciliations\Pages;

use App\Exceptions\BankReconciliationException;
use App\Filament\Farm\Resources\BankReconciliations\BankReconciliationResource;
use App\Models\BankReconciliation;
use App\Services\BankReconciliationService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditBankReconciliation extends EditRecord
{
    protected static string $resource = BankReconciliationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * Date, solde et notes passent par le service (date après le dernier relevé clôturé, jamais
     * avant une transaction pointée) ; le compte ne change pas une fois le rapprochement ouvert.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var BankReconciliation $record */
        try {
            return app(BankReconciliationService::class)->update(
                $record,
                $data['statement_date'],
                (float) $data['statement_balance'],
                $data['notes'] ?? null,
            );
        } catch (BankReconciliationException $e) {
            Notification::make()
                ->title('Modification impossible')
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
