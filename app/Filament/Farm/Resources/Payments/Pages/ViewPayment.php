<?php

namespace App\Filament\Farm\Resources\Payments\Pages;

use App\Filament\Farm\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
