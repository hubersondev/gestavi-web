<?php

namespace App\Filament\Farm\Resources\Deliveries\Tables;

use App\Enums\DeliveryStatus;
use App\Filament\Farm\Resources\Deliveries\DeliveryResource;
use App\Models\Delivery;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DeliveriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['sale.customer', 'farm']))
            ->columns([
                TextColumn::make('delivery_number')->label('N° BL')->searchable()->sortable()->weight('bold'),
                TextColumn::make('sale.invoice_number')->label('Facture')->searchable(),
                TextColumn::make('sale.customer.name')->label('Client')->placeholder('Client comptant'),
                TextColumn::make('farm.name')->label('Ferme')->placeholder('—')->toggleable(),
                TextColumn::make('delivery_date')->label('Date prévue')->date('d/m/Y')->sortable(),
                TextColumn::make('status')->label('Statut')->badge(),
                TextColumn::make('driver_name')->label('Livreur')->placeholder('—')->toggleable(),
                TextColumn::make('delivered_at')->label('Livrée le')->dateTime('d/m/Y H:i')->placeholder('—')->toggleable(),
            ])
            ->defaultSort('delivery_date', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Statut')->options(DeliveryStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn ($record) => DeliveryResource::canEdit($record)),
                self::markDeliveredAction(),
            ]);
    }

    public static function markDeliveredAction(): Action
    {
        return Action::make('mark_delivered')
            ->label('Marquer livrée')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Delivery $record): bool => $record->isOpen() && DeliveryResource::canPerformAction('mark_delivered'))
            ->form([
                TextInput::make('recipient_name')->label('Reçu par')->default(fn (Delivery $record) => $record->recipient_name)->maxLength(255),
                Textarea::make('signature_note')->label('Mention de réception (signature, observations)')->rows(2),
            ])
            ->action(function (Delivery $record, array $data): void {
                $record->markDelivered($data['recipient_name'] ?? null, $data['signature_note'] ?? null);

                Notification::make()->title("Livraison {$record->delivery_number} marquée livrée.")->success()->send();
            });
    }
}
