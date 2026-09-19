<?php

namespace App\Filament\Farm\Resources\Leaves\Pages;

use App\Filament\Farm\Resources\Leaves\LeaveResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLeaves extends ListRecords
{
    protected static string $resource = LeaveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->visible(fn () => LeaveResource::canCreate()),
        ];
    }
}
