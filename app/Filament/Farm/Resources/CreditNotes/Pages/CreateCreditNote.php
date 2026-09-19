<?php

namespace App\Filament\Farm\Resources\CreditNotes\Pages;

use App\Filament\Farm\Resources\CreditNotes\CreditNoteResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateCreditNote extends CreateRecord
{
    protected static string $resource = CreditNoteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organisation_id'] = Filament::getTenant()?->getKey();
        $data['user_id'] ??= auth()->id();

        return $data;
    }
}
