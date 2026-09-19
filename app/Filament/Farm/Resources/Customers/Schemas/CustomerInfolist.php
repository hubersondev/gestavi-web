<?php

namespace App\Filament\Farm\Resources\Customers\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations client')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nom')
                            ->weight('bold')
                            ->size('lg'),

                        TextEntry::make('code')
                            ->label('Code')
                            ->badge()
                            ->color('gray'),

                        TextEntry::make('type')
                            ->label('Type')
                            ->badge(),

                        IconEntry::make('is_active')
                            ->label('Actif')
                            ->boolean(),
                    ]),

                Section::make('Coordonnées')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('phone')
                            ->label('Téléphone')
                            ->icon('heroicon-o-phone')
                            ->placeholder('—'),

                        TextEntry::make('email')
                            ->label('Email')
                            ->icon('heroicon-o-envelope')
                            ->placeholder('—'),

                        TextEntry::make('city.name')
                            ->label('Ville')
                            ->placeholder('—'),

                        TextEntry::make('address')
                            ->label('Adresse')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Situation financière')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('credit_limit')
                            ->label('Plafond de crédit')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF'),

                        TextEntry::make('balance')
                            ->label('Solde dû')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF')
                            ->color(fn ($state): string => (float) $state > 0 ? 'danger' : 'success')
                            ->weight('bold'),

                        TextEntry::make('sales_count')
                            ->label('Total ventes')
                            ->state(fn ($record): int => $record->sales()->count())
                            ->badge()
                            ->color('info'),
                    ]),

                Section::make('Notes')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Notes internes')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
