<?php

namespace App\Filament\Farm\Pages\Forecasts;

use App\Models\FlockPrediction;
use Filament\Widgets\ChartWidget;

/**
 * Répartition des prévisions évaluées sur 30 jours par niveau de précision (écart réel/prévu).
 */
class ForecastAccuracyWidget extends ChartWidget
{
    public ?int $organisationId = null;

    public ?int $farmId = null;

    public ?int $flockId = null;

    protected ?string $heading = 'Précision des prévisions (30 jours)';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        if (! $this->organisationId) {
            return ['datasets' => [], 'labels' => []];
        }

        $counts = ['excellent' => 0, 'good' => 0, 'fair' => 0, 'poor' => 0, 'pending' => 0];

        FlockPrediction::query()
            ->where('organisation_id', $this->organisationId)
            ->when($this->farmId, fn ($q, $farmId) => $q->where('farm_id', $farmId))
            ->when($this->flockId, fn ($q, $flockId) => $q->where('flock_id', $flockId))
            ->where('prediction_date', '>=', now()->subDays(30)->toDateString())
            ->get()
            ->each(function (FlockPrediction $p) use (&$counts): void {
                $counts[$p->accuracyLevel()]++;
            });

        return [
            'datasets' => [[
                'data' => array_values($counts),
                'backgroundColor' => ['#15803d', '#4ade80', '#b45309', '#be123c', '#9ca3af'],
            ]],
            'labels' => array_map(
                fn (string $level, int $count): string => FlockPrediction::accuracyLabel($level)." ({$count})",
                array_keys($counts),
                array_values($counts),
            ),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return ['plugins' => ['legend' => ['position' => 'bottom']]];
    }
}
