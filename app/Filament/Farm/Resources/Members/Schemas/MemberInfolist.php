<?php

namespace App\Filament\Farm\Resources\Members\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MemberInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Utilisateur')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('Nom'),
                        TextEntry::make('user.email')
                            ->label('Adresse e-mail'),
                        TextEntry::make('user.phone')
                            ->label('Téléphone')
                            ->placeholder('-'),
                    ]),

                Section::make('Adhésion')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('organisationRole.name')
                            ->label('Rôle')
                            ->badge(),
                        TextEntry::make('status')
                            ->label('Statut')
                            ->badge()
                            ->color(fn ($state) => $state->getColor()),
                        TextEntry::make('joined_at')
                            ->label("Date d'adhésion")
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('farms.name')
                            ->label('Fermes assignées')
                            ->badge()
                            ->visible(fn ($record): bool => ! $record->canAccessAllFarms()),
                        TextEntry::make('farms_access')
                            ->label('Accès fermes')
                            ->state('Toutes les fermes')
                            ->badge()
                            ->color('info')
                            ->visible(fn ($record): bool => $record->canAccessAllFarms()),
                    ]),

                /*Section::make('Dates')
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
                    ]),*/
            ]);
    }
}
