<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\DispatchesOrganisationAlerts;
use App\Enums\FarmLogCategory;
use App\Enums\FarmLogPriority;
use App\Models\FarmLog;
use App\Models\Organisation;
use App\Services\AlertRecipients;
use App\Services\Forecast\ForecastService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Prévisions quotidiennes : valeurs réelles des prévisions échues, nouvelles prévisions à J+7 pour
 * chaque bande active, puis alerte « production » aux responsables pour les bandes dont la
 * trajectoire s'écarte de la courbe de leur souche (une alerte par organisation et par jour).
 */
class Forecast extends Command
{
    use DispatchesOrganisationAlerts;

    protected $signature = 'gestavi:forecast';

    protected $description = 'Calcule les prévisions à 7 jours des bandes actives et alerte sur les écarts à la souche.';

    public function handle(ForecastService $service): int
    {
        $this->info('Prévisions des bandes actives…');
        $result = $service->processAll();

        $this->info(sprintf(
            '%d bande(s) : %d prévision(s) créée(s), %d évaluée(s).',
            $result['flocks'],
            $result['created'],
            $result['evaluated'],
        ));

        $anomalies = $result['anomalies'];

        if ($anomalies === []) {
            return self::SUCCESS;
        }

        $this->dispatchAlerts(
            alertKey: 'forecast_degradation',
            collect: fn (Organisation $organisation): Collection => collect($anomalies[$organisation->id] ?? []),
            describe: function (Organisation $organisation, Collection $items): array {
                $this->logAnomalies($items);

                return [
                    'title' => $items->count().' bande(s) dont la prévision s\'écarte de la souche',
                    'lines' => $this->summarize($items, fn (array $anomaly): string => sprintf(
                        '%s — %s%s',
                        $anomaly['flock']->code,
                        $anomaly['message'],
                        $anomaly['flock']->farm ? ' — '.$anomaly['flock']->farm->name : '',
                    )),
                ];
            },
            status: 'warning',
            icon: 'heroicon-o-presentation-chart-line',
        );

        return self::SUCCESS;
    }

    /**
     * Trace chaque écart dans le journal de la ferme, pour qu'il reste visible et commentable.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     */
    private function logAnomalies(Collection $items): void
    {
        foreach ($items as $anomaly) {
            $flock = $anomaly['flock'];
            // Le journal exige un auteur : le premier responsable de l'organisation signe l'entrée automatique
            $author = AlertRecipients::globalAccess((int) $flock->organisation_id)->first();

            if (! $author) {
                continue;
            }

            FarmLog::query()->create([
                'organisation_id' => $flock->organisation_id,
                'farm_id' => $flock->farm_id,
                'flock_id' => $flock->id,
                'building_id' => $flock->building_id,
                'recorded_by' => $author->id,
                'log_date' => now()->toDateString(),
                'title' => 'Prévision défavorable — '.$flock->code,
                'category' => FarmLogCategory::Production,
                'priority' => FarmLogPriority::High,
                'description' => $anomaly['message'].'. Prévision calculée à partir des 14 derniers relevés et de la courbe de la souche.',
            ]);
        }
    }
}
