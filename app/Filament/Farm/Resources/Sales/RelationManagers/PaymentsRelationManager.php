<?php

namespace App\Filament\Farm\Resources\Sales\RelationManagers;

use App\Enums\PaymentMethod;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Historique des paiements';

    protected static ?string $recordTitleAttribute = 'id';

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Paiement')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('payment_date')
                            ->label('Date')
                            ->date('d/m/Y'),

                        TextEntry::make('amount')
                            ->label('Montant')
                            ->numeric(thousandsSeparator: ' ')
                            ->suffix(' XOF')
                            ->weight('bold')
                            ->color('success'),

                        TextEntry::make('method')
                            ->label('Mode')
                            ->badge(),

                        TextEntry::make('account.name')
                            ->label('Compte')
                            ->placeholder('—'),

                        TextEntry::make('reference')
                            ->label('Référence')
                            ->placeholder('—'),

                        TextEntry::make('user.name')
                            ->label('Enregistré par')
                            ->placeholder('—'),

                        TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('payment_date', 'desc')
            ->columns([
                TextColumn::make('payment_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Montant')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' XOF')
                    ->sortable()
                    ->weight('bold')
                    ->color('success')
                    ->summarize(Sum::make()->label('Total payé')->numeric(thousandsSeparator: ' ')),

                TextColumn::make('method')
                    ->label('Mode')
                    ->badge(),

                TextColumn::make('account.name')
                    ->label('Compte')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('reference')
                    ->label('Référence')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),

                IconColumn::make('is_recorded')
                    ->label('Comptabilisé')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('user.name')
                    ->label('Saisi par')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('method')
                    ->label('Mode')
                    ->options(PaymentMethod::class)
                    ->multiple(),
            ])
            ->headerActions([])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
