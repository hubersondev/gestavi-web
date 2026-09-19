<?php

namespace App\Filament\Farm\Resources\DailyRecords\Pages;

use App\Filament\Farm\Resources\DailyRecords\DailyRecordResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDailyRecord extends ViewRecord
{
    protected static string $resource = DailyRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn () => DailyRecordResource::canEdit($this->record)),
            DeleteAction::make()
                ->visible(fn () => DailyRecordResource::canDelete($this->record)),
        ];
    }
}
