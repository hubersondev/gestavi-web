<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserType;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations générales')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nom'),
                        TextEntry::make('email')
                            ->label('Adresse e-mail'),
                        TextEntry::make('phone')
                            ->label('Téléphone')
                            ->placeholder('-'),
                        TextEntry::make('type')
                            ->label('Type')
                            ->badge(),
                        TextEntry::make('platformRoles.name')
                            ->label('Rôle plateforme')
                            ->badge()
                            ->placeholder('-')
                            ->visible(fn ($record): bool => $record->type === UserType::System),
                        IconEntry::make('is_active')
                            ->label('Actif')
                            ->boolean(),
                        TextEntry::make('email_verified_at')
                            ->label('E-mail vérifié le')
                            ->dateTime()
                            ->placeholder('Non vérifié'),
                    ]),
                Section::make('Organisations')
                    ->schema([
                        TextEntry::make('organisations.name')
                            ->label('')
                            ->badge()
                            ->placeholder('Aucune organisation'),
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
                        TextEntry::make('deleted_at')
                            ->label('Date de suppression')
                            ->dateTime()
                            ->placeholder('-')
                            ->visible(fn ($record): bool => $record->trashed()),
                    ]),
            ]);
    }
}
