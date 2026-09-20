<?php

namespace App\Filament\Farm\Resources\Deliveries\Pages;

use App\Filament\Farm\Resources\Deliveries\DeliveryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDeliveries extends ListRecords
{
    protected static string $resource = DeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->visible(fn () => DeliveryResource::canCreate()),
        ];
    }
}
