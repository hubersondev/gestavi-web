<?php

namespace App\Filament\Farm\Resources\DailyRecords\Pages;

use App\Filament\Farm\Resources\DailyRecords\DailyRecordResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDailyRecord extends EditRecord
{
    protected static string $resource = DailyRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
