<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\DispatchesOrganisationAlerts;
use App\Models\Organisation;
use App\Models\Vaccination;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class AlertOverdueVaccinations extends Command
{
    use DispatchesOrganisationAlerts;

    protected $signature = 'gestavi:alert-overdue-vaccinations';

    protected $description = 'Notifie les responsables des vaccinations/traitements en retard (une alerte par organisation et par jour).';

    public function handle(): int
    {
        $this->info('Analyse des vaccinations en retard…');

        $this->dispatchAlerts(
            alertKey: 'overdue_vaccinations',
            collect: fn (Organisation $organisation): Collection => Vaccination::query()
                ->with('flock')
                ->overdue()
                ->whereHas('flock', fn ($q) => $q->active()->whereHas('building.farm', fn ($f) => $f->where('organisation_id', $organisation->id)))
                ->orderBy('scheduled_date')
                ->get(),
            describe: fn (Organisation $organisation, Collection $items): array => [
                'title' => $items->count().' vaccination(s) en retard',
                'lines' => $this->summarize($items, fn (Vaccination $v): string => sprintf(
                    '%s — bande %s, prévue le %s (%d j de retard)',
                    $v->treatment_name,
                    $v->flock?->code ?? '—',
                    $v->scheduled_date?->format('d/m/Y') ?? '—',
                    $v->getDaysOverdue(),
                )),
            ],
            status: 'danger',
            icon: 'heroicon-o-shield-exclamation',
        );

        return self::SUCCESS;
    }
}
