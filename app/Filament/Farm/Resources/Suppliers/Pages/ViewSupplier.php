<?php

namespace App\Filament\Farm\Resources\Suppliers\Pages;

use App\Filament\Farm\Resources\Suppliers\SupplierResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSupplier extends ViewRecord
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn () => SupplierResource::canEdit($this->record)),
        ];
    }
}
