<?php

namespace App\Filament\Farm\Resources\JournalEntries\Pages;

use App\Filament\Farm\Resources\JournalEntries\JournalEntryResource;
use Filament\Resources\Pages\ListRecords;

class ListJournalEntries extends ListRecords
{
    protected static string $resource = JournalEntryResource::class;
}
