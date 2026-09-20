<?php

namespace App\Filament\Farm\Resources\Products\Pages;

use App\Filament\Farm\Resources\Products\ProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->visible(fn () => ProductResource::canCreate()),
        ];
    }
}
