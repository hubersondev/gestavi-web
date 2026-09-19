<?php

namespace App\Filament\Farm\Resources\HealthPrograms\Pages;

use App\Filament\Farm\Resources\HealthPrograms\HealthProgramResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHealthPrograms extends ListRecords
{
    protected static string $resource = HealthProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->visible(fn () => HealthProgramResource::canCreate()),
        ];
    }
}
