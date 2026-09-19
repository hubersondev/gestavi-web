<?php

namespace App\Filament\Farm\Resources\Expenses\Pages;

use App\Filament\Farm\Resources\Expenses\ExpenseResource;
use App\Services\ExpenseService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $payNow = (bool) ($data['pay_now'] ?? false);
        unset($data['pay_now']);

        $data['organisation_id'] = Filament::getTenant()?->getKey();
        $data['user_id'] ??= auth()->id();

        try {
            return app(ExpenseService::class)->recordExpense($data, payNow: $payNow);
        } catch (Throwable $e) {
            Notification::make()
                ->title('Impossible de créer la dépense')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }
    }
}
