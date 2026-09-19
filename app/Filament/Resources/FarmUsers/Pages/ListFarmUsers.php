<?php

namespace App\Filament\Resources\FarmUsers\Pages;

use App\Filament\Resources\FarmUsers\FarmUserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFarmUsers extends ListRecords
{
    protected static string $resource = FarmUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
