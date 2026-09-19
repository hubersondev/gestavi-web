<?php

namespace App\Filament\Farm\Resources\Attendances\Pages;

use App\Filament\Farm\Resources\Attendances\AttendanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAttendances extends ListRecords
{
    protected static string $resource = AttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->visible(fn () => AttendanceResource::canCreate()),
        ];
    }
}
