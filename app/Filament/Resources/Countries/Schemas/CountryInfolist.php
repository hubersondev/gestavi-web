<?php

namespace App\Filament\Resources\Countries\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CountryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label('Nom'),
                TextEntry::make('code')
                    ->label('Code ISO alpha-2'),
                TextEntry::make('code_iso3')
                    ->label('Code ISO alpha-3')
                    ->placeholder('-'),
                TextEntry::make('phone_code')
                    ->label('Indicatif téléphonique')
                    ->placeholder('-'),
                TextEntry::make('currency.name')
                    ->label('Devise')
                    ->placeholder('-'),
                TextEntry::make('default_language')
                    ->label('Langue par défaut')
                    ->placeholder('-'),
                IconEntry::make('is_active')
                    ->label('Actif')
                    ->boolean(),
                TextEntry::make('cities_count')
                    ->label('Nombre de villes')
                    ->state(fn ($record) => $record->cities()->count()),
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
