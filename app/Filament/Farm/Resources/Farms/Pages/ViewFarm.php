<?php

namespace App\Filament\Farm\Resources\Farms\Pages;

use App\Filament\Farm\Resources\Farms\FarmResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFarm extends ViewRecord
{
    protected static string $resource = FarmResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn () => FarmResource::canEdit($this->record)),
        ];
    }
}
