<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\DailyReportNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Envoie le rapport quotidien par e-mail aux utilisateurs qui l'ont gardé activé dans leurs
 * préférences de notification (par défaut : oui).
 */
class DailyReport extends Command
{
    protected $signature = 'gestavi:daily-report {--date= : Journée rapportée (AAAA-MM-JJ), la veille par défaut}';

    protected $description = 'Envoie le rapport quotidien par e-mail aux utilisateurs qui l\'ont activé.';

    public function handle(): int
    {
        $day = $this->option('date') ? Carbon::parse($this->option('date')) : now()->subDay();
        $sent = 0;

        User::query()
            ->where('is_active', true)
            ->whereNotNull('email')
            ->whereHas('activeOrganisations')
            ->where(fn ($query) => $query
                ->whereDoesntHave('notificationPreferences')
                ->orWhereHas('notificationPreferences', fn ($prefs) => $prefs->where('email_daily_report', true)))
            ->chunkById(100, function ($users) use ($day, &$sent): void {
                foreach ($users as $user) {
                    $user->notify(new DailyReportNotification($user, $day));
                    $sent++;
                }
            });

        $this->info("Rapport du {$day->format('d/m/Y')} : {$sent} destinataire(s).");

        return self::SUCCESS;
    }
}
