<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\DispatchesOrganisationAlerts;
use App\Models\Organisation;
use App\Models\Sale;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class AlertOverdueInvoices extends Command
{
    use DispatchesOrganisationAlerts;

    protected $signature = 'gestavi:alert-overdue-invoices';

    protected $description = 'Notifie les responsables des factures échues non réglées.';

    public function handle(): int
    {
        $this->info('Analyse des factures échues…');

        $this->dispatchAlerts(
            alertKey: 'overdue_invoices',
            collect: fn (Organisation $organisation): Collection => Sale::query()
                ->with('customer')
                ->where('organisation_id', $organisation->id)
                ->overdue()
                ->orderBy('due_date')
                ->get(),
            describe: function (Organisation $organisation, Collection $items): array {
                $total = (float) $items->sum('balance');

                return [
                    'title' => sprintf('%d facture(s) échue(s) — %s XOF à recouvrer', $items->count(), number_format($total, 0, ',', ' ')),
                    'lines' => $this->summarize($items, fn (Sale $sale): string => sprintf(
                        '%s — %s, %s XOF dus, échue le %s',
                        $sale->invoice_number,
                        $sale->customer?->name ?? 'Comptant',
                        number_format((float) $sale->balance, 0, ',', ' '),
                        $sale->due_date?->format('d/m/Y') ?? '—',
                    )),
                ];
            },
            status: 'danger',
            icon: 'heroicon-o-banknotes',
        );

        return self::SUCCESS;
    }
}
