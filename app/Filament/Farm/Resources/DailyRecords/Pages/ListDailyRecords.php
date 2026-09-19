<?php

namespace App\Filament\Farm\Resources\DailyRecords\Pages;

use App\Filament\Farm\Resources\DailyRecords\DailyRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDailyRecords extends ListRecords
{
    protected static string $resource = DailyRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->visible(fn () => DailyRecordResource::canCreate()),
        ];
    }
}
