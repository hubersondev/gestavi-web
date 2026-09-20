<?php

namespace App\Filament\Farm\Resources\FiscalYears\Pages;

use App\Filament\Farm\Resources\FiscalYears\FiscalYearResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFiscalYears extends ListRecords
{
    protected static string $resource = FiscalYearResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
