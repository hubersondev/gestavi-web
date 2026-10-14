<?php

namespace App\Filament\Farm\Resources\Payments\Tables;

use App\Enums\PaymentMethod;
use App\Services\CurrentFiscalYear;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return app(CurrentFiscalYear::class)->applyToTable($table, 'payment_date')
            ->defaultSort('payment_date', 'desc')
            ->columns([
                TextColumn::make('payment_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('sale.invoice_number')
                    ->label('Facture')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('sale.customer.name')
                    ->label('Client')
                    ->placeholder('Comptant')
                    ->searchable(),

                TextColumn::make('amount')
                    ->label('Montant')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' XOF')
                    ->sortable()
                    ->weight('bold')
                    ->color('success')
                    ->summarize(Sum::make()->label('Total')->numeric(thousandsSeparator: ' ')),

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
                    ->toggleable(isToggledHiddenByDefault: true),

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
                    ->label('Mode de paiement')
                    ->options(PaymentMethod::class)
                    ->multiple(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
