<?php

namespace App\Filament\Farm\Resources\Deliveries\Pages;

use App\Filament\Farm\Resources\Deliveries\DeliveryResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDelivery extends EditRecord
{
    protected static string $resource = DeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }
}
