<?php

namespace App\Filament\Resources\ChickenTypes\Pages;

use App\Filament\Resources\ChickenTypes\ChickenTypeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewChickenType extends ViewRecord
{
    protected static string $resource = ChickenTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
