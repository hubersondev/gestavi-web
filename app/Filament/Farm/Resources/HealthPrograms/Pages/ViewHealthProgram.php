<?php

namespace App\Filament\Farm\Resources\HealthPrograms\Pages;

use App\Filament\Farm\Resources\HealthPrograms\HealthProgramResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewHealthProgram extends ViewRecord
{
    protected static string $resource = HealthProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn () => HealthProgramResource::canEdit($this->record)),
            DeleteAction::make()
                ->visible(fn () => HealthProgramResource::canDelete($this->record)),
        ];
    }
}
