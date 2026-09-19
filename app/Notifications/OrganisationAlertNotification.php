<?php

namespace App\Notifications;

use Filament\Notifications\Actions\Action;
use Filament\Notifications\DatabaseNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alerte d'organisation envoyée aux membres à accès global :
 * - canal `database` au format Filament (visible dans la cloche du panel) ;
 * - canal `mail` uniquement si un vrai mailer est configuré (`MAIL_MAILER` ≠ `log`),
 *   afin de ne pas polluer les journaux en local.
 *
 * Utilisée par les commandes planifiées gestavi:alert-* et par les exports.
 */
class OrganisationAlertNotification extends Notification
{
    use Queueable;

    /**
     * @param  list<string>  $lines  Lignes de détail (déjà formatées)
     */
    public function __construct(
        public readonly string $title,
        public readonly array $lines,
        public readonly string $alertKey,
        public readonly int $organisationId,
        public readonly string $status = 'warning',
        public readonly string $icon = 'heroicon-o-bell-alert',
        public readonly ?string $actionLabel = null,
        public readonly ?string $actionUrl = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (static::mailEnabled() && filled($notifiable->email ?? null)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public static function mailEnabled(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array', null], true);
    }

    /**
     * Stockée sous le type Filament pour être rendue dans la cloche du panel.
     */
    public function databaseType(object $notifiable): string
    {
        return DatabaseNotification::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $notification = FilamentNotification::make()
            ->title($this->title)
            ->body(implode("\n", $this->lines))
            ->status($this->status)
            ->icon($this->icon);

        if ($this->actionUrl) {
            $notification->actions([
                Action::make('open')
                    ->label($this->actionLabel ?? 'Ouvrir')
                    ->url($this->actionUrl, shouldOpenInNewTab: true)
                    ->button(),
            ]);
        }

        return array_merge($notification->getDatabaseMessage(), [
            'alert_key' => $this->alertKey,
            'organisation_id' => $this->organisationId,
        ]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('[GESTAVI] '.$this->title)
            ->greeting('Bonjour '.($notifiable->name ?? ''))
            ->line($this->title);

        foreach ($this->lines as $line) {
            $mail->line($line);
        }

        if ($this->actionUrl) {
            $mail->action($this->actionLabel ?? 'Ouvrir GESTAVI', $this->actionUrl);
        }

        return $mail->salutation('L\'équipe GESTAVI');
    }
}
