<?php

namespace App\Filament\Farm\Resources\StockCategories\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StockCategoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Catégorie')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')->label('Nom')->weight('bold')->size('lg'),
                        TextEntry::make('type')->label('Type')->badge(),
                        TextEntry::make('slug')->label('Slug')->badge()->color('gray'),
                        TextEntry::make('description')->label('Description')->placeholder('—')->columnSpanFull(),
                    ]),
            ]);
    }
}
