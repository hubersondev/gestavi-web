<?php

namespace App\Filament\Resources\Currencies\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CurrencyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label('Nom'),
                TextEntry::make('code')
                    ->label('Code ISO 4217'),
                TextEntry::make('symbol')
                    ->label('Symbole'),
                TextEntry::make('decimal_places')
                    ->label('Décimales'),
                IconEntry::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextEntry::make('countries_count')
                    ->label('Nombre de pays')
                    ->state(fn ($record) => $record->countries()->count()),
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
