<?php

namespace App\Filament\Farm\Resources\FiscalYears\Pages;

use App\Exceptions\AccountingException;
use App\Filament\Farm\Resources\FiscalYears\FiscalYearResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditFiscalYear extends EditRecord
{
    protected static string $resource = FiscalYearResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        try {
            FiscalYearResource::assertNoOverlap($data, $this->record->getKey());
        } catch (AccountingException $e) {
            Notification::make()->title('Exercice invalide')->body($e->getMessage())->danger()->send();
            $this->halt();
        }

        return $data;
    }
}
