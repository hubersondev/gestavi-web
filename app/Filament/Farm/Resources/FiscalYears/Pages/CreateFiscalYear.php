<?php

namespace App\Filament\Farm\Resources\FiscalYears\Pages;

use App\Enums\FiscalYearStatus;
use App\Exceptions\AccountingException;
use App\Filament\Farm\Resources\FiscalYears\FiscalYearResource;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateFiscalYear extends CreateRecord
{
    protected static string $resource = FiscalYearResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organisation_id'] = Filament::getTenant()?->getKey();
        $data['status'] = FiscalYearStatus::Open;

        try {
            FiscalYearResource::assertNoOverlap($data);
        } catch (AccountingException $e) {
            Notification::make()->title('Exercice invalide')->body($e->getMessage())->danger()->send();
            $this->halt();
        }

        return $data;
    }
}
