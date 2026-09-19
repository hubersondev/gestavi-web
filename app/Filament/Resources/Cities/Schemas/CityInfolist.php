<?php

namespace App\Filament\Resources\Cities\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CityInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label('Nom'),
                TextEntry::make('country.name')
                    ->label('Pays'),
                IconEntry::make('is_active')
                    ->label('Actif')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->label('Date de création')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->label('Date de modification')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
