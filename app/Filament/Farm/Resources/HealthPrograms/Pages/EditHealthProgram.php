<?php

namespace App\Filament\Farm\Resources\HealthPrograms\Pages;

use App\Filament\Farm\Resources\HealthPrograms\HealthProgramResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditHealthProgram extends EditRecord
{
    protected static string $resource = HealthProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
