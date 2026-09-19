<?php

namespace App\Filament\Farm\Resources\Payrolls\Pages;

use App\Filament\Farm\Resources\Payrolls\PayrollResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPayroll extends EditRecord
{
    protected static string $resource = PayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * Les autres retenues saisies modifient le net : on recalcule les totaux.
     */
    protected function afterSave(): void
    {
        $this->record->recalculate();
    }
}
