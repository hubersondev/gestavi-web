<?php

namespace App\Filament\Farm\Resources\Buildings\Pages;

use App\Filament\Farm\Resources\Buildings\BuildingResource;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateBuilding extends CreateRecord
{
    protected static string $resource = BuildingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['farm_id'])) {
            $membership = Filament::auth()->user()?->membershipIn(Filament::getTenant());

            if ($membership) {
                $data['farm_id'] = app(CurrentFarm::class)->get($membership)?->id;
            }
        }

        return $data;
    }
}
