<?php

namespace App\Console\Commands;

use App\Enums\StockLotStatus;
use App\Models\StockLot;
use Illuminate\Console\Command;

class ExpireStockLots extends Command
{
    protected $signature = 'gestavi:expire-stock-lots
                            {--dry-run : Affiche les lots concernés sans modifier la base}';

    protected $description = 'Marque comme "Périmé" les lots de stock dont la date de péremption est dépassée.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = StockLot::query()
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now()->toDateString())
            ->whereNotIn('status', [StockLotStatus::Expired, StockLotStatus::Depleted, StockLotStatus::Blocked]);

        $count = $query->count();

        if ($count === 0) {
            $this->info('Aucun lot à expirer.');

            return self::SUCCESS;
        }

        $this->info("Lots détectés : {$count}");

        if ($dryRun) {
            $query->with('stock')->chunk(100, function ($lots): void {
                foreach ($lots as $lot) {
                    $this->line(sprintf(
                        '  - Lot %s (%s) — péremption %s — qté restante %s',
                        $lot->lot_number,
                        $lot->stock?->name ?? '—',
                        $lot->expiry_date?->format('d/m/Y') ?? '—',
                        rtrim(rtrim(number_format((float) $lot->current_quantity, 2, ',', ' '), '0'), ','),
                    ));
                }
            });

            $this->warn('Mode dry-run : aucune modification appliquée.');

            return self::SUCCESS;
        }

        $updated = $query->update(['status' => StockLotStatus::Expired->value]);

        $this->info("{$updated} lot(s) marqué(s) comme périmé(s).");

        return self::SUCCESS;
    }
}
