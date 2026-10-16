<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

/**
 * Abonnements : expire les abonnements échus (retour au plan gratuit) et prévient les
 * responsables des échéances proches (J-7, J-3, J-1). Planifié chaque matin.
 */
class ProcessSubscriptions extends Command
{
    protected $signature = 'gestavi:subscriptions';

    protected $description = 'Expire les abonnements échus et alerte sur les échéances proches.';

    public function handle(SubscriptionService $service): int
    {
        $this->info('Traitement des abonnements…');

        $result = $service->processExpirations();

        $this->info(sprintf('%d abonnement(s) expiré(s), %d alerte(s) d\'échéance envoyée(s).', $result['expired'], $result['alerts']));

        return self::SUCCESS;
    }
}
