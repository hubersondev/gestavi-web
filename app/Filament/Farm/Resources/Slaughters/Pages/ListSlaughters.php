<?php

namespace App\Filament\Farm\Resources\Slaughters\Pages;

use App\Filament\Farm\Resources\Slaughters\SlaughterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSlaughters extends ListRecords
{
    protected static string $resource = SlaughterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->visible(fn () => SlaughterResource::canCreate()),
        ];
    }
}
