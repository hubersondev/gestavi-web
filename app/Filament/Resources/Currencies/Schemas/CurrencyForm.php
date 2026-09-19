<?php

namespace App\Filament\Resources\Currencies\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CurrencyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Franc CFA BCEAO'),
                TextInput::make('code')
                    ->label('Code ISO 4217')
                    ->required()
                    ->maxLength(3)
                    ->unique(ignoreRecord: true)
                    ->placeholder('XOF'),
                TextInput::make('symbol')
                    ->label('Symbole')
                    ->required()
                    ->maxLength(10)
                    ->placeholder('FCFA'),
                TextInput::make('decimal_places')
                    ->label('Décimales')
                    ->numeric()
                    ->required()
                    ->default(2)
                    ->minValue(0)
                    ->maxValue(4),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
