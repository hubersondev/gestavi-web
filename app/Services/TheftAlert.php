<?php

namespace App\Services;

use App\Notifications\OrganisationAlertNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Un vol saisi (sujets, stock, matériel, caisse) prévient aussitôt les responsables de
 * l'organisation : alerte critique, donc cloche, e-mail et SMS selon leurs préférences.
 */
class TheftAlert
{
    public const ALERT_KEY = 'theft';

    /**
     * @param  list<string>  $lines
     */
    public static function dispatch(int $organisationId, string $title, array $lines, ?string $actionUrl = null, ?string $actionLabel = null): void
    {
        $recipients = AlertRecipients::globalAccess($organisationId);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new OrganisationAlertNotification(
            title: $title,
            lines: $lines,
            alertKey: self::ALERT_KEY,
            organisationId: $organisationId,
            status: 'danger',
            icon: 'heroicon-o-shield-exclamation',
            actionLabel: $actionLabel,
            actionUrl: $actionUrl,
        ));
    }

    /**
     * Montant ou quantité lisible : 1 250 000.
     */
    public static function number(float $value, int $decimals = 0): string
    {
        return number_format($value, $decimals, ',', ' ');
    }
}
