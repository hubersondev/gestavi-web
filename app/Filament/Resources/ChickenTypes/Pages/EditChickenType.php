<?php

namespace App\Filament\Resources\ChickenTypes\Pages;

use App\Filament\Resources\ChickenTypes\ChickenTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditChickenType extends EditRecord
{
    protected static string $resource = ChickenTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
