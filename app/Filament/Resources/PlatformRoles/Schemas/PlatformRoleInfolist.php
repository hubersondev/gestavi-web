<?php

namespace App\Filament\Resources\PlatformRoles\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PlatformRoleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations du rôle')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nom'),
                        TextEntry::make('slug')
                            ->label('Identifiant'),
                        TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        IconEntry::make('is_system')
                            ->label('Rôle système')
                            ->boolean(),
                        TextEntry::make('users_count')
                            ->label('Nombre d\'utilisateurs')
                            ->state(fn ($record) => $record->users()->count()),
                    ]),
                Section::make('Permissions')
                    ->schema([
                        TextEntry::make('permissions.name')
                            ->label('')
                            ->badge()
                            ->placeholder('Aucune permission'),
                    ]),
                Section::make('Dates')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Date de création')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->label('Date de modification')
                            ->dateTime()
                            ->placeholder('-'),
                    ]),
            ]);
    }
}
