<?php

namespace App\Filament\Farm\Resources\Suppliers\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SupplierInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations générales')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('code')
                            ->label('Code')
                            ->placeholder('-'),
                        TextEntry::make('name')
                            ->label('Raison sociale'),
                        TextEntry::make('type')
                            ->label('Type')
                            ->badge()
                            ->formatStateUsing(fn ($record) => $record->getTypeLabel())
                            ->color(fn ($state) => match ($state) {
                                'aliment' => 'success',
                                'medicament' => 'danger',
                                'poussin' => 'warning',
                                'materiel' => 'info',
                                'equipement' => 'primary',
                                default => 'gray',
                            }),
                        TextEntry::make('farm.name')
                            ->label('Ferme'),
                        IconEntry::make('is_active')
                            ->label('Actif')
                            ->boolean(),
                    ]),

                Section::make('Contact')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('contact_name')
                            ->label('Nom du contact')
                            ->placeholder('-'),
                        TextEntry::make('phone')
                            ->label('Téléphone')
                            ->placeholder('-'),
                        TextEntry::make('phone_secondary')
                            ->label('Tél. secondaire')
                            ->placeholder('-'),
                        TextEntry::make('email')
                            ->label('Email')
                            ->placeholder('-'),
                    ]),

                Section::make('Adresse')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('address')
                            ->label('Adresse')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('city.name')
                            ->label('Ville')
                            ->placeholder('-'),
                        TextEntry::make('country.name')
                            ->label('Pays')
                            ->placeholder('-'),
                    ]),

                Section::make('Informations fiscales & bancaires')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('tax_id')
                            ->label('N° contribuable')
                            ->placeholder('-'),
                        TextEntry::make('payment_terms')
                            ->label('Conditions de paiement')
                            ->placeholder('-'),
                        TextEntry::make('bank_name')
                            ->label('Banque')
                            ->placeholder('-'),
                        TextEntry::make('bank_account')
                            ->label('N° de compte')
                            ->placeholder('-'),
                    ]),

                Section::make('Notes')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),

                Section::make('Dates')
                    ->columns(2)
                    ->schema([
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
