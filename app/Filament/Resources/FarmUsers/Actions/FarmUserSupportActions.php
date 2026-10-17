<?php

namespace App\Filament\Resources\FarmUsers\Actions;

use App\Models\User;
use App\Services\Impersonation;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Password;

/**
 * Outils de support sur un utilisateur ferme : se connecter en tant que lui, lui envoyer un lien
 * de réinitialisation de mot de passe, réinitialiser sa double authentification perdue, révoquer
 * ses appareils mobiles. Chaque geste est journalisé dans le journal d'audit.
 */
class FarmUserSupportActions
{
    /**
     * @return list<Action>
     */
    public static function make(): array
    {
        return [
            Action::make('impersonate')
                ->label('Se connecter en tant que')
                ->icon('heroicon-o-arrow-right-end-on-rectangle')
                ->color('info')
                ->visible(fn (User $record): bool => Impersonation::canImpersonate(Filament::auth()->user(), $record))
                ->requiresConfirmation()
                ->modalDescription('Vous verrez l\'espace de cet utilisateur tel qu\'il le voit. Vos actions seront enregistrées à son nom, avec votre identifiant dans le journal d\'audit. Un bandeau vous permettra de revenir.')
                ->action(fn (User $record) => redirect()->to(Impersonation::start(Filament::auth()->user(), $record))),

            Action::make('sendResetLink')
                ->label('Envoyer un lien de mot de passe')
                ->icon('heroicon-o-key')
                ->color('gray')
                ->visible(fn (User $record): bool => Filament::auth()->user()?->hasPermission('users.update') && filled($record->email))
                ->requiresConfirmation()
                ->modalDescription(fn (User $record): string => 'Un courriel de réinitialisation sera envoyé à '.$record->email.'. Le lien est valable 60 minutes.')
                ->action(function (User $record): void {
                    $status = Password::broker()->sendResetLink(['email' => $record->email]);

                    if ($status === Password::RESET_LINK_SENT) {
                        self::audit($record, 'password_reset_link_sent', 'Lien de réinitialisation de mot de passe envoyé');
                        Notification::make()->title('Lien envoyé à '.$record->email)->success()->send();

                        return;
                    }

                    Notification::make()->title('Envoi impossible')->body(__($status))->danger()->send();
                }),

            Action::make('resetTwoFactor')
                ->label('Réinitialiser la double authentification')
                ->icon('heroicon-o-shield-exclamation')
                ->color('warning')
                ->visible(fn (User $record): bool => Filament::auth()->user()?->hasPermission('users.update') && $record->hasTwoFactorEnabled())
                ->requiresConfirmation()
                ->modalDescription('L\'application d\'authentification et les codes de secours actuels ne fonctionneront plus. L\'utilisateur devra en configurer une nouvelle à sa prochaine connexion.')
                ->action(function (User $record): void {
                    $record->saveAppAuthenticationSecret(null);
                    $record->saveAppAuthenticationRecoveryCodes(null);
                    self::audit($record, 'two_factor_reset', 'Double authentification réinitialisée');
                    Notification::make()->title('Double authentification réinitialisée')->success()->send();
                }),

            Action::make('revokeTokens')
                ->label(fn (User $record): string => 'Déconnecter les appareils mobiles ('.$record->tokens()->count().')')
                ->icon('heroicon-o-device-phone-mobile')
                ->color('danger')
                ->visible(fn (User $record): bool => Filament::auth()->user()?->hasPermission('users.update') && $record->tokens()->exists())
                ->requiresConfirmation()
                ->modalDescription('Tous les jetons de l\'application mobile seront révoqués : l\'utilisateur devra se reconnecter sur chaque appareil. Les saisies hors-ligne en attente sur ses appareils seront envoyées à sa reconnexion.')
                ->action(function (User $record): void {
                    $count = $record->tokens()->count();
                    $record->tokens()->delete();
                    self::audit($record, 'tokens_revoked', $count.' appareil(s) mobile(s) déconnecté(s)');
                    Notification::make()->title($count.' appareil(s) déconnecté(s)')->success()->send();
                }),
        ];
    }

    private static function audit(User $target, string $event, string $description): void
    {
        activity('gestavi')
            ->causedBy(Filament::auth()->user())
            ->performedOn($target)
            ->event($event)
            ->withProperties(['attributes' => ['email' => $target->email]])
            ->log($description);
    }
}
