<?php

namespace App\Console\Commands;

use App\Services\FarmMetricsAggregator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AggregateFarmMetrics extends Command
{
    protected $signature = 'gestavi:aggregate-farm-metrics
                            {--from= : Date de début (Y-m-d), défaut : hier}
                            {--to= : Date de fin (Y-m-d), défaut : aujourd\'hui}
                            {--organisation= : Limiter à une organisation}';

    protected $description = 'Calcule les agrégats quotidiens par ferme (production, ventes, encaissements, dépenses) pour le tableau de bord groupe';

    public function handle(FarmMetricsAggregator $aggregator): int
    {
        $from = $this->option('from') ? Carbon::parse($this->option('from')) : now()->subDay();
        $to = $this->option('to') ? Carbon::parse($this->option('to')) : now();

        $written = $aggregator->aggregate($from, $to, $this->option('organisation') ? (int) $this->option('organisation') : null);

        $this->info(sprintf('%d agrégat(s) ferme/jour calculé(s) du %s au %s.', $written, $from->toDateString(), $to->toDateString()));

        return self::SUCCESS;
    }
}
