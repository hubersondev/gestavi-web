<?php

namespace App\Filament\Farm\Resources\Flocks\Pages;

use App\Filament\Farm\Resources\Flocks\FlockResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFlocks extends ListRecords
{
    protected static string $resource = FlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->visible(fn () => FlockResource::canCreate()),
        ];
    }
}
