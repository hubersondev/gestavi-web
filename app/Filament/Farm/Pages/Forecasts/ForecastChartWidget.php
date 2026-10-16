<?php

namespace App\Filament\Farm\Pages\Forecasts;

use App\Enums\PredictionType;
use App\Models\BreedStandard;
use App\Models\DailyRecord;
use App\Models\Flock;
use App\Models\FlockPrediction;
use App\Services\Forecast\ForecastService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Courbe « réel / prévision / courbe de la souche » d'une bande sur 7 jours passés et 7 à venir.
 * Rendu dans la page Prévisions avec la bande et la grandeur choisies.
 */
class ForecastChartWidget extends ChartWidget
{
    public ?int $flockId = null;

    public string $type = 'laying_rate';

    protected ?string $maxHeight = '340px';

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        $type = PredictionType::tryFrom($this->type);

        return $type ? $type->getLabel().' : réel, prévision et souche (14 jours)' : 'Prévisions';
    }

    protected function getData(): array
    {
        $flock = $this->flockId ? Flock::query()->find($this->flockId) : null;
        $type = PredictionType::tryFrom($this->type);

        if (! $flock || ! $type) {
            return ['datasets' => [], 'labels' => []];
        }

        $start = Carbon::today()->subDays(7);
        $end = Carbon::today()->addDays(ForecastService::HORIZON_DAYS);
        $service = app(ForecastService::class);
        $records = $flock->dailyRecords()->orderBy('record_date')->get();
        $byDate = $records->keyBy(fn (DailyRecord $r): string => $r->record_date->toDateString());

        $predictions = FlockPrediction::query()
            ->where('flock_id', $flock->id)
            ->forType($type)
            ->whereBetween('target_date', [$start, $end])
            ->get()
            ->keyBy(fn (FlockPrediction $p): string => $p->target_date->toDateString());

        $labels = [];
        $actual = [];
        $predicted = [];
        $standard = [];
        $currentAge = (int) ($flock->getCurrentAgeInDays() ?? 0);

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->toDateString();
            $labels[] = $date->format('d/m');

            $record = $byDate->get($key);
            $actual[] = $record && $date->lte(Carbon::today())
                ? $service->metricValue($flock, $type, $record, $records)
                : null;

            $prediction = $predictions->get($key);
            $predicted[] = $prediction ? (float) $prediction->predicted_value : null;

            $age = $currentAge + (int) Carbon::today()->diffInDays($date, false);
            $standard[] = BreedStandard::expectedValue($flock->breed, $flock->productionKind(), $type->standardColumn(), max(0, $age));
        }

        return [
            'datasets' => [
                [
                    'label' => 'Réel',
                    'data' => $actual,
                    'borderColor' => '#15803d',
                    'backgroundColor' => 'rgba(21, 128, 61, 0.12)',
                    'pointRadius' => 4,
                    'fill' => false,
                    'spanGaps' => false,
                ],
                [
                    'label' => 'Prévision',
                    'data' => $predicted,
                    'borderColor' => '#1d4ed8',
                    'backgroundColor' => 'rgba(29, 78, 216, 0.1)',
                    'borderDash' => [6, 4],
                    'pointRadius' => 3,
                    'fill' => false,
                    'spanGaps' => true,
                ],
                [
                    'label' => 'Courbe de la souche',
                    'data' => $standard,
                    'borderColor' => '#9ca3af',
                    'borderDash' => [2, 4],
                    'pointRadius' => 0,
                    'fill' => false,
                    'spanGaps' => true,
                ],
            ],
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
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => ['y' => ['beginAtZero' => false]],
        ];
    }
}
