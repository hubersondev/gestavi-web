<?php

namespace App\Filament\Farm\Resources\Accounts\Pages;

use App\Filament\Farm\Resources\Accounts\AccountResource;
use App\Models\Account;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewAccount extends ViewRecord
{
    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('setDefault')
                ->label('Définir par défaut')
                ->icon('heroicon-o-star')
                ->color('warning')
                ->visible(fn (Account $record): bool => ! $record->isDefault() && AccountResource::canPerformAction('set_default'))
                ->requiresConfirmation()
                ->action(function (Account $record): void {
                    $record->setAsDefault();

                    Notification::make()
                        ->title("{$record->name} est maintenant le compte par défaut.")
                        ->success()
                        ->send();
                }),

            Action::make('recalculateBalance')
                ->label('Recalculer le solde')
                ->icon('heroicon-o-calculator')
                ->color('info')
                ->visible(fn (): bool => AccountResource::canPerformAction('recalculate_balance'))
                ->requiresConfirmation()
                ->modalDescription('Recalcule le solde à partir de toutes les transactions, transaction d\'ouverture comprise.')
                ->action(function (Account $record): void {
                    $record->recalculateBalance();

                    Notification::make()
                        ->title('Solde recalculé.')
                        ->body('Nouveau solde : '.number_format((float) $record->fresh()->current_balance, 0, ',', ' ').' XOF')
                        ->success()
                        ->send();
                }),

            EditAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
