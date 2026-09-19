<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\DispatchesOrganisationAlerts;
use App\Models\Organisation;
use App\Models\Stock;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class AlertLowStock extends Command
{
    use DispatchesOrganisationAlerts;

    protected $signature = 'gestavi:alert-low-stock';

    protected $description = 'Notifie les responsables des produits en stock sous leur seuil d\'alerte.';

    public function handle(): int
    {
        $this->info('Analyse des stocks sous le seuil…');

        $this->dispatchAlerts(
            alertKey: 'low_stock',
            collect: fn (Organisation $organisation): Collection => Stock::query()
                ->with('farm')
                ->where('organisation_id', $organisation->id)
                ->active()
                ->lowStock()
                ->orderByRaw('(quantity / NULLIF(min_quantity, 0)) ASC')
                ->get(),
            describe: fn (Organisation $organisation, Collection $items): array => [
                'title' => $items->count().' produit(s) sous le seuil d\'alerte',
                'lines' => $this->summarize($items, fn (Stock $stock): string => sprintf(
                    '%s — %s %s restant(s) (seuil %s)%s',
                    $stock->name,
                    number_format((float) $stock->quantity, 0, ',', ' '),
                    $stock->unit,
                    number_format((float) $stock->min_quantity, 0, ',', ' '),
                    $stock->farm ? ' — '.$stock->farm->name : '',
                )),
            ],
            status: 'warning',
            icon: 'heroicon-o-archive-box-x-mark',
        );

        return self::SUCCESS;
    }
}
