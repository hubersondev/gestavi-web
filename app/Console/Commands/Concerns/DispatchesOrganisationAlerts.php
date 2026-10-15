<?php

namespace App\Console\Commands\Concerns;

use App\Models\AlertDispatch;
use App\Models\Organisation;
use App\Notifications\OrganisationAlertNotification;
use App\Services\AlertRecipients;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Socle commun des commandes gestavi:alert-* :
 * - destinataires = membres actifs à accès global (owner/admin) de l'organisation ;
 * - idempotence : une alerte par organisation et par jour (table alert_dispatches) ;
 * - envoi via OrganisationAlertNotification (base Filament + e-mail si configuré).
 */
trait DispatchesOrganisationAlerts
{
    /**
     * @return Collection<int, User>
     */
    protected function globalAccessRecipients(Organisation $organisation): Collection
    {
        return AlertRecipients::globalAccess($organisation->id);
    }

    /**
     * Parcourt les organisations, calcule les éléments à signaler via $collect,
     * puis notifie une fois par jour. Retourne [organisations traitées, notifications].
     *
     * @param  callable(Organisation): Collection  $collect
     * @param  callable(Organisation, Collection): array{title: string, lines: list<string>}  $describe
     * @return array{0: int, 1: int}
     */
    protected function dispatchAlerts(string $alertKey, callable $collect, callable $describe, string $status = 'warning', string $icon = 'heroicon-o-bell-alert'): array
    {
        $organisations = 0;
        $notifications = 0;

        Organisation::query()->chunkById(50, function ($chunk) use ($alertKey, $collect, $describe, $status, $icon, &$organisations, &$notifications): void {
            foreach ($chunk as $organisation) {
                $items = $collect($organisation);

                if ($items->isEmpty()) {
                    continue;
                }

                if (AlertDispatch::alreadySentToday($organisation->id, $alertKey)) {
                    $this->line("  - {$organisation->name} : alerte déjà envoyée aujourd'hui, ignorée.");

                    continue;
                }

                $recipients = $this->globalAccessRecipients($organisation);

                if ($recipients->isEmpty()) {
                    continue;
                }

                $message = $describe($organisation, $items);

                Notification::send($recipients, new OrganisationAlertNotification(
                    title: $message['title'],
                    lines: $message['lines'],
                    alertKey: $alertKey,
                    organisationId: $organisation->id,
                    status: $status,
                    icon: $icon,
                    actionLabel: $message['action_label'] ?? null,
                    actionUrl: $message['action_url'] ?? null,
                ));

                AlertDispatch::record($organisation->id, $alertKey, $items->count(), $recipients->count());

                $organisations++;
                $notifications += $recipients->count();

                $this->line("  - {$organisation->name} : {$items->count()} élément(s) → {$recipients->count()} notification(s)");
            }
        });

        if ($organisations === 0) {
            $this->info('Aucune organisation concernée.');
        } else {
            $this->info("{$organisations} organisation(s) traitée(s), {$notifications} notification(s) envoyée(s).");
        }

        return [$organisations, $notifications];
    }

    /**
     * Résumé « • ligne » limité à $max éléments, avec compteur du reste.
     *
     * @return list<string>
     */
    protected function summarize(Collection $items, callable $format, int $max = 5): array
    {
        $lines = $items->take($max)->map(fn ($item): string => '• '.$format($item))->values()->all();

        if ($items->count() > $max) {
            $lines[] = '+ '.($items->count() - $max).' autre(s)…';
        }

        return $lines;
    }
}
