<?php

namespace App\Filament\Farm\Resources\FarmLogs\Pages;

use App\Filament\Farm\Resources\FarmLogs\FarmLogResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditFarmLog extends EditRecord
{
    protected static string $resource = FarmLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
