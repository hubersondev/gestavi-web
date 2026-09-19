<?php

namespace App\Filament\Farm\Resources\Buildings\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BuildingInfolist
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
                        TextEntry::make('farm.name')
                            ->label('Ferme'),
                        TextEntry::make('type')
                            ->label('Type')
                            ->placeholder('-'),
                        TextEntry::make('capacity')
                            ->label('Capacité')
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('area_sqm')
                            ->label('Surface (m²)')
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        IconEntry::make('is_active')
                            ->label('Actif')
                            ->boolean(),
                    ])
                    ->columnSpanFull(),
                /*Section::make('Statut')
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
                    ]),*/
            ]);
    }
}
