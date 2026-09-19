<?php

namespace App\Filament\Farm\Resources\Members\Pages;

use App\Filament\Farm\Resources\Members\MemberResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Ajouter un utilisateur')
                ->visible(fn () => MemberResource::canCreate()),
        ];
    }
}
