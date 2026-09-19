<?php

namespace App\Filament\Farm\Resources\Suppliers\Pages;

use App\Filament\Farm\Resources\Suppliers\SupplierResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSuppliers extends ListRecords
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->visible(fn () => SupplierResource::canCreate()),
        ];
    }
}
