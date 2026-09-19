<?php

namespace App\Filament\Farm\Resources\Farms\Pages;

use App\Filament\Farm\Resources\Farms\FarmResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateFarm extends CreateRecord
{
    protected static string $resource = FarmResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organisation_id'] = Filament::getTenant()->id;

        return $data;
    }
}
