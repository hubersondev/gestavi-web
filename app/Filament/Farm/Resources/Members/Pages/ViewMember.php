<?php

namespace App\Filament\Farm\Resources\Members\Pages;

use App\Filament\Farm\Resources\Members\MemberResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Password;

class ViewMember extends ViewRecord
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetPassword')
                ->label('Réinitialiser le mot de passe')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Réinitialiser le mot de passe')
                ->modalDescription(fn () => 'Un e-mail de réinitialisation sera envoyé à '.$this->record->user->email.'.')
                ->visible(function (): bool {
                    if ($this->record->user_id === Filament::auth()->user()?->id) {
                        return false;
                    }

                    return MemberResource::canPerformAction('reset_password');
                })
                ->action(function () {
                    $status = Password::sendResetLink(['email' => $this->record->user->email]);

                    if ($status === Password::RESET_LINK_SENT) {
                        Notification::make()
                            ->success()
                            ->title('E-mail envoyé')
                            ->body('Un lien de réinitialisation a été envoyé à '.$this->record->user->email.'.')
                            ->send();
                    } else {
                        Notification::make()
                            ->danger()
                            ->title('Échec de l\'envoi')
                            ->body('Impossible d\'envoyer l\'e-mail de réinitialisation.')
                            ->send();
                    }
                }),

            EditAction::make()
                ->visible(fn () => MemberResource::canEdit($this->record)),
        ];
    }
}
