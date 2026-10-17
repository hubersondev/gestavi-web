<?php

namespace App\Filament\Resources\FarmUsers\Schemas;

use App\Models\User;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class FarmUserInfolist
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
                        IconEntry::make('is_active')
                            ->label('Actif')
                            ->boolean(),
                    ]),
                Section::make('Sécurité et appareils')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('two_factor')
                            ->label('Double authentification')
                            ->state(fn (User $record): string => $record->hasTwoFactorEnabled() ? 'Activée' : 'Non configurée')
                            ->badge()
                            ->color(fn (User $record): string => $record->hasTwoFactorEnabled() ? 'success' : 'gray'),
                        TextEntry::make('devices')
                            ->label('Appareils mobiles connectés')
                            ->state(fn (User $record): string => (string) $record->tokens()->count()),
                        TextEntry::make('last_device_use')
                            ->label('Dernier usage mobile')
                            ->state(fn (User $record): ?string => ($last = $record->tokens()->max('last_used_at')) ? Carbon::parse($last)->diffForHumans() : null)
                            ->placeholder('jamais'),
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
