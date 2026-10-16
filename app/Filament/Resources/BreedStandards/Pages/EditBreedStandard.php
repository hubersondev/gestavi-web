<?php

namespace App\Filament\Resources\BreedStandards\Pages;

use App\Filament\Resources\BreedStandards\BreedStandardResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBreedStandard extends EditRecord
{
    protected static string $resource = BreedStandardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
