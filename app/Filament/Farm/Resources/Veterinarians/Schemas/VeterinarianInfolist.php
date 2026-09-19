<?php

namespace App\Filament\Farm\Resources\Veterinarians\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VeterinarianInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations générales')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nom complet'),

                        TextEntry::make('specialty')
                            ->label('Spécialité')
                            ->placeholder('-'),

                        TextEntry::make('license')
                            ->label("Numéro d'ordre / Licence")
                            ->placeholder('-'),

                        IconEntry::make('is_active')
                            ->label('Actif')
                            ->boolean(),
                    ]),

                Section::make('Coordonnées')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('clinic')
                            ->label('Cabinet / Clinique')
                            ->placeholder('-')
                            ->columnSpanFull(),

                        TextEntry::make('phone')
                            ->label('Téléphone')
                            ->placeholder('-'),

                        TextEntry::make('email')
                            ->label('Email')
                            ->placeholder('-'),
                    ]),

                Section::make('Notes')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('')
                            ->placeholder('Aucune note.')
                            ->columnSpanFull(),
                    ])
                    ->hidden(fn ($record) => empty($record?->notes)),

                Section::make('Historique')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Date de création')
                            ->dateTime(),

                        TextEntry::make('updated_at')
                            ->label('Dernière modification')
                            ->dateTime(),
                    ])
                    ->collapsed(),
            ]);
    }
}
