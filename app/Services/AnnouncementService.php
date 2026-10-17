<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Organisation;
use App\Models\User;
use App\Notifications\OrganisationAlertNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Envoi d'une annonce en notification aux responsables (propriétaires, administrateurs) des
 * organisations visées. Retourne le nombre de destinataires.
 */
class AnnouncementService
{
    public function notify(Announcement $announcement, ?User $by = null): int
    {
        $recipients = 0;

        $announcement->targetOrganisations()->chunkById(100, function ($organisations) use ($announcement, &$recipients): void {
            foreach ($organisations as $organisation) {
                /** @var Organisation $organisation */
                $users = AlertRecipients::globalAccess($organisation->id);

                if ($users->isEmpty()) {
                    continue;
                }

                Notification::send($users, new OrganisationAlertNotification(
                    title: $announcement->title,
                    lines: collect(preg_split('/\R/', trim($announcement->body)) ?: [])->filter()->map(fn (string $l): string => '• '.$l)->values()->all(),
                    alertKey: 'announcement',
                    organisationId: $organisation->id,
                    status: $announcement->level->notificationStatus(),
                    icon: 'heroicon-o-megaphone',
                ));

                $recipients += $users->count();
            }
        });

        $announcement->forceFill(['notified_at' => now()])->save();

        return $recipients;
    }
}
