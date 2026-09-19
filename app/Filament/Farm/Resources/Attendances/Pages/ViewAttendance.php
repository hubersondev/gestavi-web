<?php

namespace App\Filament\Farm\Resources\Attendances\Pages;

use App\Filament\Farm\Resources\Attendances\AttendanceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAttendance extends ViewRecord
{
    protected static string $resource = AttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->visible(fn () => AttendanceResource::canEdit($this->record)),
            DeleteAction::make()->visible(fn () => AttendanceResource::canDelete($this->record)),
        ];
    }
}
