<?php

namespace App\Filament\Farm\Resources\VetVisits\Pages;

use App\Filament\Farm\Resources\VetVisits\VetVisitResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditVetVisit extends EditRecord
{
    protected static string $resource = VetVisitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
