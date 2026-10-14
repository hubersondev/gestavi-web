<?php

namespace App\Console\Commands;

use App\Filament\Farm\Resources\Flocks\FlockResource;
use App\Filament\Farm\Resources\Sales\SaleResource;
use App\Models\Flock;
use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\Sale;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/**
 * Banc de charge : ouvre chaque page du panel Farm pour une organisation donnée par des requêtes
 * HTTP internes, chronomètre chacune, compte ses requêtes SQL et relève les requêtes les plus
 * coûteuses. Écrit un rapport Markdown dans storage/app/load-test/.
 */
class LoadMeasure extends Command
{
    protected $signature = 'gestavi:load-measure
                            {--organisation= : Organisation à mesurer (défaut : celle qui a le plus de fermes)}
                            {--limit=0 : Nombre maximal de pages (0 = toutes)}
                            {--rounds=2 : Passages par page ; la première ouverture chauffe les caches}';

    protected $description = 'Mesure le temps de réponse et les requêtes SQL de chaque page du panel Farm';

    /** @var array<string, array{count: int, time: float, example: string}> */
    private array $queries = [];

    public function handle(Kernel $kernel): int
    {
        ini_set('memory_limit', '4G');

        $organisation = $this->resolveOrganisation();

        if (! $organisation) {
            $this->error('Aucune organisation à mesurer.');

            return self::FAILURE;
        }

        $owner = $this->owner($organisation);

        if (! $owner) {
            $this->error("L'organisation {$organisation->id} n'a pas de propriétaire.");

            return self::FAILURE;
        }

        $this->info("Organisation {$organisation->id} « {$organisation->name} », propriétaire {$owner->email}");
        $this->table(['Table', 'Lignes de cette organisation'], collect(['farms', 'flocks', 'daily_records', 'sales', 'expenses', 'transactions', 'journal_entries'])
            ->map(fn (string $table): array => [$table, number_format(DB::table($table)->where('organisation_id', $organisation->id)->count(), 0, ',', ' ')])
            ->all());

        Auth::guard('web')->login($owner);

        $urls = $this->panelUrls($organisation);
        $limit = (int) $this->option('limit');

        if ($limit > 0) {
            $urls = array_slice($urls, 0, $limit, true);
        }

        $rounds = max(1, (int) $this->option('rounds'));
        $results = [];
        $redirector = app('redirect');

        DB::listen(function (QueryExecuted $query): void {
            $key = $this->normalize($query->sql);
            $this->queries[$key] ??= ['count' => 0, 'time' => 0.0, 'example' => mb_substr($query->sql, 0, 400)];
            $this->queries[$key]['count']++;
            $this->queries[$key]['time'] += $query->time;
        });

        $bar = $this->output->createProgressBar(count($urls) * $rounds);

        foreach ($urls as $name => $url) {
            $durations = [];
            $queryCount = 0;
            $queryTime = 0.0;
            $status = 0;

            for ($round = 1; $round <= $rounds; $round++) {
                $before = $this->snapshot();
                $start = microtime(true);

                try {
                    $response = $kernel->handle(Request::create($url, 'GET', server: ['HTTP_ACCEPT' => 'text/html']));
                    $status = $response->getStatusCode();
                } catch (\Throwable $e) {
                    $status = 500;
                    $this->warn("\n{$name} : ".$e::class.' — '.mb_substr($e->getMessage(), 0, 200));
                } finally {
                    app()->instance('redirect', $redirector);
                }

                $durations[] = (microtime(true) - $start) * 1000;
                [$queryCount, $queryTime] = $this->delta($before);
                $bar->advance();
            }

            // Dernier passage : caches chauds, mesure représentative d'une navigation réelle
            $results[] = ['page' => $name, 'status' => $status, 'ms' => end($durations), 'first_ms' => $durations[0], 'queries' => $queryCount, 'sql_ms' => $queryTime];
        }

        $bar->finish();
        $this->newLine(2);

        usort($results, fn (array $a, array $b): int => $b['ms'] <=> $a['ms']);

        $this->table(['Page', 'HTTP', 'ms (chaud)', 'ms (1er)', 'Requêtes', 'SQL ms'], array_map(fn (array $row): array => [
            $row['page'], $row['status'], number_format($row['ms'], 0), number_format($row['first_ms'], 0), $row['queries'], number_format($row['sql_ms'], 0),
        ], array_slice($results, 0, 25)));

        $topQueries = collect($this->queries)->sortByDesc('time')->take(15);

        $this->newLine();
        $this->info('Requêtes SQL les plus coûteuses (cumul sur toutes les pages)');
        $this->table(['Cumul ms', 'Exécutions', 'Requête'], $topQueries->map(fn (array $q): array => [number_format($q['time'], 0), $q['count'], mb_substr($q['example'], 0, 140)])->values()->all());

        $path = $this->writeReport($organisation, $results, $topQueries->all());
        $this->info("Rapport : {$path}");

        return self::SUCCESS;
    }

    private function resolveOrganisation(): ?Organisation
    {
        if ($this->option('organisation')) {
            return Organisation::query()->find((int) $this->option('organisation'));
        }

        $largest = DB::table('farms')->select('organisation_id', DB::raw('count(*) as farms'))->groupBy('organisation_id')->orderByDesc('farms')->orderBy('organisation_id')->first();

        return $largest ? Organisation::query()->find($largest->organisation_id) : null;
    }

    private function owner(Organisation $organisation): ?User
    {
        $membership = OrganisationUser::query()
            ->where('organisation_id', $organisation->id)
            ->whereHas('organisationRole', fn ($q) => $q->where('slug', 'owner'))
            ->first();

        return $membership?->user;
    }

    /**
     * Pages sans paramètre d'enregistrement du panel Farm, plus la fiche d'une bande et d'une facture.
     *
     * @return array<string, string>
     */
    private function panelUrls(Organisation $organisation): array
    {
        $urls = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();

            if (! str_starts_with($name, 'filament.farm.') || str_contains($name, '.auth.') || $name === 'filament.farm.tenant' || ! in_array('GET', $route->methods(), true)) {
                continue;
            }

            if (array_diff($route->parameterNames(), ['tenant']) !== []) {
                continue;
            }

            $urls[str_replace('filament.farm.', '', $name)] = route($name, ['tenant' => $organisation]);
        }

        ksort($urls);

        $panel = Filament::getPanel('farm');
        Filament::setCurrentPanel($panel);
        Filament::setTenant($organisation);

        if ($flock = Flock::query()->where('organisation_id', $organisation->id)->where('status', 'active')->first()) {
            $urls['resources.flocks.view'] = FlockResource::getUrl('view', ['record' => $flock], tenant: $organisation);
        }

        if ($sale = Sale::query()->where('organisation_id', $organisation->id)->first()) {
            $urls['resources.sales.view'] = SaleResource::getUrl('view', ['record' => $sale], tenant: $organisation);
        }

        return $urls;
    }

    /**
     * @return array{0: int, 1: float}
     */
    private function snapshot(): array
    {
        return [array_sum(array_column($this->queries, 'count')), array_sum(array_column($this->queries, 'time'))];
    }

    /**
     * @param  array{0: int, 1: float}  $before
     * @return array{0: int, 1: float}
     */
    private function delta(array $before): array
    {
        [$count, $time] = $this->snapshot();

        return [$count - $before[0], $time - $before[1]];
    }

    private function normalize(string $sql): string
    {
        return (string) preg_replace(['/\d+/', '/\s+/', '/in \((\?, ?)+\?\)/i'], ['N', ' ', 'in (...)'], $sql);
    }

    /**
     * @param  list<array{page: string, status: int, ms: float, first_ms: float, queries: int, sql_ms: float}>  $results
     * @param  array<string, array{count: int, time: float, example: string}>  $topQueries
     */
    private function writeReport(Organisation $organisation, array $results, array $topQueries): string
    {
        $lines = ['# Banc de charge — organisation '.$organisation->id.' — '.now()->format('d/m/Y H:i'), ''];
        $lines[] = '| Page | HTTP | ms (chaud) | ms (1er) | Requêtes | SQL ms |';
        $lines[] = '|---|---|---|---|---|---|';

        foreach ($results as $row) {
            $lines[] = sprintf('| %s | %d | %d | %d | %d | %d |', $row['page'], $row['status'], $row['ms'], $row['first_ms'], $row['queries'], $row['sql_ms']);
        }

        $lines[] = '';
        $lines[] = '## Requêtes SQL les plus coûteuses';
        $lines[] = '';
        $lines[] = '| Cumul ms | Exécutions | Requête |';
        $lines[] = '|---|---|---|';

        foreach ($topQueries as $query) {
            $lines[] = sprintf('| %d | %d | `%s` |', $query['time'], $query['count'], str_replace('|', '\\|', $query['example']));
        }

        $path = 'load-test/rapport-'.now()->format('Ymd-His').'.md';
        Storage::disk('local')->put($path, implode("\n", $lines));

        return Storage::disk('local')->path($path);
    }
}
