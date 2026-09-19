<?php

namespace App\Filament\Farm\Resources\Leaves\Pages;

use App\Filament\Farm\Resources\Leaves\LeaveResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLeave extends ViewRecord
{
    protected static string $resource = LeaveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->visible(fn () => $this->record->isPending() && LeaveResource::canEdit($this->record)),
            DeleteAction::make()->visible(fn () => LeaveResource::canDelete($this->record)),
        ];
    }
}
