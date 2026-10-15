<?php

namespace App\Notifications;

use App\Models\DailyRecord;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\Sale;
use App\Models\Stock;
use App\Models\User;
use App\Models\Vaccination;
use App\Services\CurrentFarm;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Rapport quotidien par e-mail (07h00) : pour chaque ferme accessible, l'activité de la veille
 * (effectif, œufs, pertes, ventes) et ce qui attend (stocks bas, vaccinations en retard).
 */
class DailyReportNotification extends Notification
{
    use Queueable;

    /** @var list<array<string, mixed>> */
    public readonly array $farms;

    public function __construct(User $user, public readonly CarbonInterface $day)
    {
        $this->farms = self::buildFarms($user, $day);
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return OrganisationAlertNotification::mailEnabled() && filled($notifiable->email ?? null) ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('[GESTAVI] Rapport quotidien du '.$this->day->format('d/m/Y'))
            ->greeting('Bonjour '.($notifiable->name ?? ''))
            ->line('Voici l\'activité de vos fermes pour la journée du '.$this->day->format('d/m/Y').'.');

        foreach ($this->farms as $farm) {
            $mail->line('**'.$farm['organisation'].' — '.$farm['name'].'**');
            $mail->line(sprintf(
                '• %d bande(s) active(s), %s sujets présents',
                $farm['active_flocks'],
                number_format($farm['birds'], 0, ',', ' '),
            ));
            $mail->line(sprintf(
                '• Hier : %s œufs collectés, %d perte(s), %d vente(s) pour %s XOF',
                number_format($farm['eggs'], 0, ',', ' '),
                $farm['losses'],
                $farm['sales_count'],
                number_format($farm['sales_total'], 0, ',', ' '),
            ));

            if ($farm['low_stocks'] > 0 || $farm['overdue_vaccinations'] > 0) {
                $mail->line(sprintf(
                    '• À traiter : %d stock(s) sous le seuil, %d vaccination(s) en retard',
                    $farm['low_stocks'],
                    $farm['overdue_vaccinations'],
                ));
            }
        }

        if ($this->farms === []) {
            $mail->line('Aucune ferme ne vous est accessible pour le moment.');
        }

        return $mail
            ->action('Ouvrir GESTAVI', url('/farm'))
            ->salutation('L\'équipe GESTAVI');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function buildFarms(User $user, CarbonInterface $day): array
    {
        $farms = [];

        $memberships = OrganisationUser::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->with(['organisation', 'organisationRole'])
            ->get();

        foreach ($memberships as $membership) {
            /** @var Organisation|null $organisation */
            $organisation = $membership->organisation;

            if (! $organisation) {
                continue;
            }

            $farmIds = $membership->canAccessAllFarms()
                ? null
                : app(CurrentFarm::class)->getAccessibleFarms($membership)->pluck('id')->all();

            $query = Farm::query()->where('organisation_id', $organisation->id)->orderBy('name');

            if ($farmIds !== null) {
                $query->whereIn('id', $farmIds);
            }

            foreach ($query->get() as $farm) {
                $farms[] = self::describeFarm($organisation, $farm, $day);
            }
        }

        return $farms;
    }

    /**
     * @return array<string, mixed>
     */
    private static function describeFarm(Organisation $organisation, Farm $farm, CarbonInterface $day): array
    {
        $flocks = Flock::query()->where('farm_id', $farm->id)->active()->get(['id', 'quantity_current']);
        $records = DailyRecord::query()
            ->whereIn('flock_id', $flocks->pluck('id'))
            ->whereDate('record_date', $day->toDateString())
            ->get(['eggs_collected', 'mortality', 'culls']);
        $sales = Sale::query()
            ->where('farm_id', $farm->id)
            ->whereDate('sale_date', $day->toDateString())
            ->where('status', '!=', 'cancelled')
            ->get(['total']);

        return [
            'organisation' => $organisation->name,
            'name' => $farm->name,
            'active_flocks' => $flocks->count(),
            'birds' => (int) $flocks->sum('quantity_current'),
            'eggs' => (int) $records->sum('eggs_collected'),
            'losses' => (int) $records->sum('mortality') + (int) $records->sum('culls'),
            'sales_count' => $sales->count(),
            'sales_total' => (float) $sales->sum('total'),
            'low_stocks' => Stock::query()->where('farm_id', $farm->id)->active()->lowStock()->count(),
            'overdue_vaccinations' => Vaccination::query()->where('farm_id', $farm->id)->overdue()->count(),
        ];
    }
}
