<?php

namespace App\Filament\Farm\Resources\Attendances\Pages;

use App\Filament\Farm\Resources\Attendances\AttendanceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAttendance extends CreateRecord
{
    protected static string $resource = AttendanceResource::class;

    protected function associateRecordWithTenant(\Illuminate\Database\Eloquent\Model $record, \Illuminate\Database\Eloquent\Model $tenant): void
    {
        // Attendance est lié au tenant via employee → organisation. Pas d'association directe.
    }
}
