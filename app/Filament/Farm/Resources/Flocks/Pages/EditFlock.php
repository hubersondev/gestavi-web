<?php

namespace App\Filament\Farm\Resources\Flocks\Pages;

use App\Filament\Farm\Resources\Flocks\FlockResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditFlock extends EditRecord
{
    protected static string $resource = FlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
