<?php

namespace App\Filament\Farm\Resources\Contracts\Pages;

use App\Filament\Farm\Resources\Contracts\ContractResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListContracts extends ListRecords
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->visible(fn () => ContractResource::canCreate()),
        ];
    }
}
