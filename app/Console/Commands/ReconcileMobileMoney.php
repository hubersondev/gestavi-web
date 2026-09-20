<?php

namespace App\Console\Commands;

use App\Models\MobileMoneyTransaction;
use App\Services\MobileMoney\MobileMoneyService;
use Illuminate\Console\Command;

class ReconcileMobileMoney extends Command
{
    protected $signature = 'gestavi:reconcile-mobile-money {--min-age= : Âge minimal en minutes (défaut : config)}';

    protected $description = 'Retente le rapprochement automatique des encaissements mobile money encore non rapprochés.';

    public function handle(MobileMoneyService $service): int
    {
        $minAge = (int) ($this->option('min-age') ?? config('mobilemoney.auto_match_min_age_minutes', 10));

        $pending = MobileMoneyTransaction::query()
            ->received()
            ->where('created_at', '<=', now()->subMinutes($minAge))
            ->orderBy('occurred_at')
            ->get();

        $matched = 0;

        foreach ($pending as $transaction) {
            if ($service->tryAutoMatch($transaction, notifyIfUnmatched: false)) {
                $matched++;
                $this->line("  - {$transaction->operator->getLabel()} {$transaction->external_id} rapproché.");
            }
        }

        $this->info(sprintf('%d transaction(s) examinée(s), %d rapprochée(s).', $pending->count(), $matched));

        return self::SUCCESS;
    }
}
