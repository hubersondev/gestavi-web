<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\DispatchesOrganisationAlerts;
use App\Enums\FarmLogCategory;
use App\Enums\FarmLogPriority;
use App\Models\FarmLog;
use App\Models\Organisation;
use App\Services\AlertRecipients;
use App\Services\Weather\WeatherService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Météo : prévisions à 5 jours de chaque ferme active (toutes les 3 heures), puis alertes aux
 * responsables quand les prochains jours dépassent les seuils de la phase de production des bandes
 * (une alerte critique et une alerte simple au plus par organisation et par jour) et trace au
 * journal de ferme.
 */
class FetchWeather extends Command
{
    use DispatchesOrganisationAlerts;

    protected $signature = 'gestavi:fetch-weather';

    protected $description = 'Récupère les prévisions météo des fermes et alerte sur les conditions à risque pour les bandes.';

    public function handle(WeatherService $service): int
    {
        if (! WeatherService::enabled()) {
            $this->info('Météo désactivée : OPENWEATHERMAP_API_KEY absente.');

            return self::SUCCESS;
        }

        $this->info('Récupération des prévisions météo…');
        $result = $service->processAll();

        $this->info(sprintf('%d ferme(s), %d journée(s) de prévision.', $result['farms'], $result['forecasts']));

        foreach ($result['errors'] as $error) {
            $this->warn('  - '.$error);
        }

        if ($result['anomalies'] === []) {
            return self::SUCCESS;
        }

        $this->logAnomalies($result['anomalies']);

        foreach ([true => ['weather_critical', 'danger'], false => ['weather_warning', 'warning']] as $critical => [$key, $status]) {
            $this->dispatchAlerts(
                alertKey: $key,
                collect: fn (Organisation $organisation): Collection => collect($result['anomalies'][$organisation->id] ?? [])
                    ->filter(fn (array $a): bool => $a['critical'] === (bool) $critical)
                    ->values(),
                describe: fn (Organisation $organisation, Collection $items): array => [
                    'title' => $critical
                        ? 'Météo critique pour '.$items->pluck('flock.id')->unique()->count().' bande(s)'
                        : 'Météo à surveiller pour '.$items->pluck('flock.id')->unique()->count().' bande(s)',
                    'lines' => $this->summarize($items, fn (array $a): string => sprintf('%s — %s — %s', $a['farm']->name, $a['flock']->code, $a['message'])),
                ],
                status: $status,
                icon: 'heroicon-o-cloud',
            );
        }

        return self::SUCCESS;
    }

    /**
     * Une entrée de journal par ferme et par jour, listant les conditions à risque.
     *
     * @param  array<int, list<array<string, mixed>>>  $anomaliesByOrganisation
     */
    private function logAnomalies(array $anomaliesByOrganisation): void
    {
        foreach ($anomaliesByOrganisation as $organisationId => $anomalies) {
            $author = AlertRecipients::globalAccess((int) $organisationId)->first();

            if (! $author) {
                continue;
            }

            foreach (collect($anomalies)->groupBy(fn (array $a) => $a['farm']->id) as $farmAnomalies) {
                $farm = $farmAnomalies->first()['farm'];
                $title = 'Alerte météo — '.$farm->name;

                $exists = FarmLog::query()
                    ->where('farm_id', $farm->id)
                    ->whereDate('log_date', now()->toDateString())
                    ->where('title', $title)
                    ->exists();

                if ($exists) {
                    continue;
                }

                FarmLog::query()->create([
                    'organisation_id' => $organisationId,
                    'farm_id' => $farm->id,
                    'recorded_by' => $author->id,
                    'log_date' => now()->toDateString(),
                    'title' => $title,
                    'category' => FarmLogCategory::Environment,
                    'priority' => $farmAnomalies->contains(fn (array $a) => $a['critical']) ? FarmLogPriority::Urgent : FarmLogPriority::High,
                    'description' => $farmAnomalies->map(fn (array $a): string => $a['flock']->code.' : '.$a['message'])->unique()->implode("\n"),
                ]);
            }
        }
    }
}
