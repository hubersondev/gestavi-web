<?php

namespace App\Filament\Farm\Resources\Veterinarians\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class VeterinarianForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Informations')
                    ->tabs([
                        Tabs\Tab::make('Informations générales')
                            ->icon('heroicon-o-user-circle')
                            ->schema([
                                Section::make('Identité')
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Nom complet')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('Dr. Jean Dupont'),

                                        TextInput::make('specialty')
                                            ->label('Spécialité')
                                            ->maxLength(255)
                                            ->placeholder('Ex: Aviculture, Pathologie aviaire'),

                                        TextInput::make('license')
                                            ->label("Numéro d'ordre / Licence")
                                            ->maxLength(100)
                                            ->placeholder('Ex: VET-2024-001'),

                                        Toggle::make('is_active')
                                            ->label('Vétérinaire actif')
                                            ->default(true)
                                            ->inline(false),
                                    ])
                                    ->columns(2),
                            ]),

                        Tabs\Tab::make('Coordonnées')
                            ->icon('heroicon-o-phone')
                            ->schema([
                                Section::make('Contact')
                                    ->schema([
                                        TextInput::make('clinic')
                                            ->label('Cabinet / Clinique')
                                            ->maxLength(255)
                                            ->placeholder('Ex: Clinique Vétérinaire du Sahel')
                                            ->columnSpanFull(),

                                        TextInput::make('phone')
                                            ->label('Téléphone')
                                            ->tel()
                                            ->maxLength(50)
                                            ->placeholder('+225 07 00 00 00 00'),

                                        TextInput::make('email')
                                            ->label('Email')
                                            ->email()
                                            ->maxLength(255)
                                            ->placeholder('vet@exemple.com'),
                                    ])
                                    ->columns(2),
                            ]),

                        Tabs\Tab::make('Notes')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        Textarea::make('notes')
                                            ->label('Notes complémentaires')
                                            ->rows(6)
                                            ->placeholder('Informations supplémentaires sur le vétérinaire...')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
