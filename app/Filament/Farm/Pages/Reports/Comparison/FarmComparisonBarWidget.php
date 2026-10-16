<?php

namespace App\Filament\Farm\Pages\Reports\Comparison;

use App\Enums\ProductionKind;
use App\Services\FarmComparisonService;
use Filament\Widgets\ChartWidget;

/**
 * Un indicateur, une barre par ferme. Rendu dans la page de comparaison multi-fermes avec les
 * fermes, la période et le type de production choisis.
 */
class FarmComparisonBarWidget extends ChartWidget
{
    public const COLORS = ['#2563eb', '#16a34a', '#f59e0b', '#dc2626', '#7c3aed'];

    public int $organisationId = 0;

    /** @var list<int> */
    public array $farmIds = [];

    public string $dateFrom = '';

    public string $dateTo = '';

    public ?string $productionKind = null;

    public string $metric = 'mortality_rate';

    protected ?string $maxHeight = '260px';

    public function getHeading(): ?string
    {
        [$label, $unit] = FarmComparisonService::METRICS[$this->metric] ?? [$this->metric, ''];

        return $unit !== '' ? "{$label} ({$unit})" : $label;
    }

    protected function getData(): array
    {
        if (count($this->farmIds) < FarmComparisonService::MIN_FARMS || $this->dateFrom === '' || $this->dateTo === '') {
            return ['datasets' => [], 'labels' => []];
        }

        $rows = app(FarmComparisonService::class)->compare(
            $this->organisationId,
            $this->farmIds,
            $this->dateFrom,
            $this->dateTo,
            $this->productionKind ? ProductionKind::tryFrom($this->productionKind) : null,
        );

        [$label] = FarmComparisonService::METRICS[$this->metric] ?? [$this->metric];

        return [
            'labels' => $rows->pluck('farm_name')->all(),
            'datasets' => [[
                'label' => $label,
                'data' => $rows->map(fn (array $r) => $r[$this->metric] === null ? 0 : (float) $r[$this->metric])->all(),
                'backgroundColor' => $rows->keys()->map(fn (int $i): string => self::COLORS[$i % count(self::COLORS)])->all(),
                'borderRadius' => 4,
            ]],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true]],
        ];
    }
}
