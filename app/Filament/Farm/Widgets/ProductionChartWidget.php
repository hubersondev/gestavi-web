<?php

namespace App\Filament\Farm\Widgets;

use App\Models\DailyRecord;
use App\Models\Flock;
use App\Services\CurrentFarm;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class ProductionChartWidget extends ChartWidget
{
    use \App\Filament\Farm\Concerns\RequiresOrganisationPermission;

    protected static string $requiredPermission = 'daily_records.view';

    protected ?string $heading = 'Production & Mortalité (30 jours)';

    protected static ?int $sort = 2;

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return ['datasets' => [], 'labels' => []];
        }

        $activeFlockIds = Flock::query()
            ->active()
            ->where('flocks.organisation_id', $tenant->getKey())->tap(fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q, 'flocks.farm_id'))
            ->pluck('id');

        if ($activeFlockIds->isEmpty()) {
            return ['datasets' => [], 'labels' => []];
        }

        $dates = collect();
        for ($i = 29; $i >= 0; $i--) {
            $dates->push(now()->subDays($i)->format('Y-m-d'));
        }

        $records = DailyRecord::query()
            ->whereIn('flock_id', $activeFlockIds)
            ->where('record_date', '>=', now()->subDays(30))
            ->selectRaw('record_date, SUM(eggs_collected) as total_eggs, SUM(mortality) as total_mortality')
            ->groupBy('record_date')
            ->orderBy('record_date')
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->record_date)->format('Y-m-d'));

        $eggs = $dates->map(fn (string $d) => (int) ($records->get($d)?->total_eggs ?? 0))->toArray();
        $mortality = $dates->map(fn (string $d) => (int) ($records->get($d)?->total_mortality ?? 0))->toArray();
        $labels = $dates->map(fn (string $d) => Carbon::parse($d)->format('d/m'))->toArray();

        $hasEggs = array_sum($eggs) > 0;

        $datasets = [
            [
                'label' => 'Mortalité',
                'data' => $mortality,
                'borderColor' => '#ef4444',
                'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                'fill' => true,
                'tension' => 0.3,
            ],
        ];

        if ($hasEggs) {
            array_unshift($datasets, [
                'label' => 'Oeufs collectés',
                'data' => $eggs,
                'borderColor' => '#10b981',
                'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                'fill' => true,
                'tension' => 0.3,
            ]);
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
        ];
    }
}
