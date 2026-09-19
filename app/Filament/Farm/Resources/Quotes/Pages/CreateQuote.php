<?php

namespace App\Filament\Farm\Resources\Quotes\Pages;

use App\Filament\Farm\Resources\Quotes\QuoteResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateQuote extends CreateRecord
{
    protected static string $resource = QuoteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organisation_id'] = Filament::getTenant()?->getKey();
        $data['user_id'] ??= auth()->id();

        return $data;
    }
}
