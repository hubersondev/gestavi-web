<?php

namespace App\Filament\Farm\Resources\CreditNotes\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CreditNoteInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Avoir')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('credit_note_number')
                            ->label('N°')
                            ->badge()
                            ->color('warning')
                            ->weight('bold'),

                        TextEntry::make('issue_date')
                            ->label('Date d\'émission')
                            ->date('d/m/Y'),

                        TextEntry::make('amount')
                            ->label('Montant')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF')
                            ->weight('bold')
                            ->size('lg'),

                        TextEntry::make('status')
                            ->label('Statut')
                            ->badge(),
                    ]),

                Section::make('Facture concernée')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('sale.invoice_number')
                            ->label('N° Facture')
                            ->badge()
                            ->color('primary'),

                        TextEntry::make('sale.total')
                            ->label('Total facture')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF'),

                        TextEntry::make('sale.balance')
                            ->label('Solde restant')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF'),
                    ]),

                Section::make('Détails')
                    ->schema([
                        TextEntry::make('reason')
                            ->label('Motif')
                            ->placeholder('—'),

                        TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('applied_at')
                            ->label('Date d\'imputation')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Non imputé'),

                        TextEntry::make('issuer.name')
                            ->label('Émis par')
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
