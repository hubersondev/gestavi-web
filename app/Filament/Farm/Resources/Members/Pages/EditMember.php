<?php

namespace App\Filament\Farm\Resources\Members\Pages;

use App\Filament\Farm\Resources\Members\MemberResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Password;

class EditMember extends EditRecord
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            Action::make('resetPassword')
                ->label('Réinitialiser le mot de passe')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Réinitialiser le mot de passe')
                ->modalDescription(fn () => 'Un e-mail de réinitialisation sera envoyé à '.$this->record->user->email.'.')
                ->visible(function (): bool {
                    $currentUser = Filament::auth()->user();

                    if ($this->record->user_id === $currentUser?->id) {
                        return false;
                    }

                    $membership = $currentUser?->membershipIn(Filament::getTenant());

                    return $membership && $membership->isAdmin();
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
            DeleteAction::make()
                ->label('Supprimer l\'utilisateur')
                ->before(function (DeleteAction $action) {
                    if ($this->record->user_id === Filament::auth()->user()?->id) {
                        Notification::make()
                            ->danger()
                            ->title('Action impossible')
                            ->body('Vous ne pouvez pas vous retirer vous-même.')
                            ->send();

                        $action->cancel();

                        return;
                    }

                    if ($this->record->isLastOwner()) {
                        Notification::make()
                            ->danger()
                            ->title('Action impossible')
                            ->body('Impossible de retirer le dernier propriétaire de l\'organisation.')
                            ->send();

                        $action->cancel();
                    }
                }),
        ];
    }

    /**
     * Pré-remplir les données du formulaire d'édition.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['farm_ids'] = $this->record->farms()->pluck('farms.id')->toArray();
        $data['user_name'] = $this->record->user->name;
        $data['user_phone'] = $this->record->user->phone;

        return $data;
    }

    /**
     * Synchroniser les fermes et mettre à jour le profil utilisateur après la sauvegarde.
     */
    protected function afterSave(): void
    {
        if ($this->record->canAccessAllFarms()) {
            $this->record->farms()->detach();
        } else {
            $this->record->farms()->sync($this->data['farm_ids'] ?? []);
        }

        $userData = array_filter([
            'name' => $this->data['user_name'] ?? null,
            'phone' => $this->data['user_phone'] ?? null,
        ], fn ($value) => $value !== null);

        if (! empty($this->data['user_password'])) {
            $userData['password'] = $this->data['user_password'];
        }

        if (! empty($userData)) {
            $this->record->user->update($userData);
        }
    }
}
