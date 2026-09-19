<?php

namespace App\Filament\Resources\Countries\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CountryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->label('Code ISO alpha-2')
                    ->required()
                    ->maxLength(2)
                    ->unique(ignoreRecord: true),
                TextInput::make('code_iso3')
                    ->label('Code ISO alpha-3')
                    ->maxLength(3)
                    ->unique(ignoreRecord: true),
                TextInput::make('phone_code')
                    ->label('Indicatif téléphonique')
                    ->maxLength(10)
                    ->placeholder('+225'),
                Select::make('currency_id')
                    ->label('Devise')
                    ->relationship('currency', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('Sélectionner une devise'),
                TextInput::make('default_language')
                    ->label('Langue par défaut')
                    ->maxLength(5)
                    ->placeholder('fr'),
                Toggle::make('is_active')
                    ->label('Actif')
                    ->default(true),
            ]);
    }
}
