<?php

namespace App\Filament\Farm\Resources\Expenses\Schemas;

use App\Models\Expense;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class ExpenseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dépense')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('expense_number')
                            ->label('N°')
                            ->badge()
                            ->color('danger')
                            ->weight('bold'),

                        TextEntry::make('expense_date')
                            ->label('Date')
                            ->date('d/m/Y'),

                        TextEntry::make('due_date')
                            ->label('Échéance')
                            ->date('d/m/Y')
                            ->placeholder('—'),

                        TextEntry::make('status')
                            ->label('Statut')
                            ->badge(),
                    ]),

                Section::make('Détails')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('category')
                            ->label('Catégorie')
                            ->badge(),

                        TextEntry::make('reference')
                            ->label('N° facture fournisseur')
                            ->placeholder('—'),

                        TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull(),
                    ]),

                Section::make('Affectation')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('supplier.name')
                            ->label('Fournisseur')
                            ->icon('heroicon-o-truck')
                            ->placeholder('—'),

                        TextEntry::make('farm.name')
                            ->label('Ferme')
                            ->icon('heroicon-o-home')
                            ->placeholder('Organisation'),

                        TextEntry::make('flock.code')
                            ->label('Bande')
                            ->placeholder('—'),
                    ]),

                Section::make('Montants')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('amount')
                            ->label('Montant total')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF')
                            ->weight('bold')
                            ->size('lg'),

                        TextEntry::make('paid_amount')
                            ->label('Payé')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF')
                            ->color('success'),

                        TextEntry::make('balance')
                            ->label('Solde dû')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF')
                            ->weight('bold')
                            ->color(fn ($state): string => (float) $state > 0 ? 'danger' : 'success'),

                        TextEntry::make('account.name')
                            ->label('Compte débité')
                            ->icon('heroicon-o-building-library')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Justificatif')
                    ->hidden(fn (Expense $record): bool => empty($record->attachment))
                    ->schema([
                        TextEntry::make('attachment')
                            ->label('Document')
                            ->formatStateUsing(fn ($state): string => basename((string) $state))
                            ->url(fn ($state): ?string => $state ? Storage::url($state) : null, shouldOpenInNewTab: true)
                            ->icon('heroicon-o-paper-clip')
                            ->columnSpanFull(),
                    ]),

                Section::make('Notes & historique')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('recorder.name')
                            ->label('Saisie par')
                            ->placeholder('—'),

                        TextEntry::make('paid_at')
                            ->label('Date de paiement complet')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),

                        TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
