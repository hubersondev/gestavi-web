<?php

namespace App\Filament\Farm\Resources\VetVisits\Pages;

use App\Filament\Farm\Resources\VetVisits\VetVisitResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVetVisits extends ListRecords
{
    protected static string $resource = VetVisitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->visible(fn () => VetVisitResource::canCreate()),
        ];
    }
}
