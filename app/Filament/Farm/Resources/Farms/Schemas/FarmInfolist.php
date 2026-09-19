<?php

namespace App\Filament\Farm\Resources\Farms\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FarmInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations générales')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('code')
                            ->label('Code'),
                        TextEntry::make('name')
                            ->label('Nom'),
                        TextEntry::make('chickenType.name')
                            ->label('Type d\'élevage')
                            ->placeholder('-'),
                        TextEntry::make('capacity')
                            ->label('Capacité')
                            ->numeric(),
                        TextEntry::make('phone')
                            ->label('Téléphone')
                            ->placeholder('-'),
                        TextEntry::make('email')
                            ->label('Email')
                            ->placeholder('-'),
                        TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
                Section::make('Localisation')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('address')
                            ->label('Adresse')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('city.name')
                            ->label('Ville')
                            ->placeholder('-'),
                        TextEntry::make('latitude')
                            ->label('Latitude')
                            ->placeholder('-'),
                        TextEntry::make('longitude')
                            ->label('Longitude')
                            ->placeholder('-'),
                    ]),
                Section::make('Statut')
                    ->schema([
                        IconEntry::make('is_active')
                            ->label('Actif')
                            ->boolean(),
                        TextEntry::make('created_at')
                            ->label('Date de création')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->label('Dernière modification')
                            ->dateTime(),
                    ]),
            ]);
    }
}
