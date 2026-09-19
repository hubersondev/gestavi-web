<?php

namespace App\Filament\Farm\Resources\Vaccinations\Pages;

use App\Filament\Farm\Resources\Vaccinations\VaccinationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVaccinations extends ListRecords
{
    protected static string $resource = VaccinationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->visible(fn () => VaccinationResource::canCreate()),
        ];
    }
}
