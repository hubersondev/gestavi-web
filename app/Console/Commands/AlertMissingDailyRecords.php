<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\DispatchesOrganisationAlerts;
use App\Models\Flock;
use App\Models\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class AlertMissingDailyRecords extends Command
{
    use DispatchesOrganisationAlerts;

    protected $signature = 'gestavi:alert-missing-daily-records
                            {--date= : Date à contrôler (défaut : hier)}';

    protected $description = 'Notifie les responsables des bandes actives sans relevé quotidien pour la veille.';

    public function handle(): int
    {
        $date = $this->option('date') ? \Carbon\Carbon::parse($this->option('date'))->toDateString() : now()->subDay()->toDateString();

        $this->info("Contrôle des relevés manquants au {$date}…");

        $this->dispatchAlerts(
            alertKey: 'missing_daily_records',
            collect: fn (Organisation $organisation): Collection => Flock::query()
                ->with('building.farm')
                ->active()
                ->whereDate('start_date', '<=', $date)
                ->whereHas('building.farm', fn ($f) => $f->where('organisation_id', $organisation->id))
                ->whereDoesntHave('dailyRecords', fn ($q) => $q->whereDate('record_date', $date))
                ->orderBy('code')
                ->get(),
            describe: fn (Organisation $organisation, Collection $items): array => [
                'title' => $items->count().' bande(s) sans relevé le '.\Carbon\Carbon::parse($date)->format('d/m/Y'),
                'lines' => $this->summarize($items, fn (Flock $flock): string => sprintf(
                    '%s (%s) — %d sujets',
                    $flock->code,
                    $flock->building?->farm?->name ?? '—',
                    (int) $flock->quantity_current,
                )),
            ],
            status: 'warning',
            icon: 'heroicon-o-clipboard-document-list',
        );

        return self::SUCCESS;
    }
}
