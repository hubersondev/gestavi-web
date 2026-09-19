<?php

namespace App\Filament\Resources\FarmUsers\Pages;

use App\Filament\Resources\FarmUsers\FarmUserResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFarmUser extends ViewRecord
{
    protected static string $resource = FarmUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
