<?php

namespace App\Filament\Farm\Pages\Reports\Comparison;

use App\Enums\ProductionKind;
use App\Services\FarmComparisonService;
use Filament\Widgets\ChartWidget;

/**
 * Radar des scores normalisés (0–100) de chaque ferme : survie, ponte, croissance, sobriété
 * aliment, ventes. 100 = la meilleure ferme de l'axe.
 */
class FarmComparisonRadarWidget extends ChartWidget
{
    public int $organisationId = 0;

    /** @var list<int> */
    public array $farmIds = [];

    public string $dateFrom = '';

    public string $dateTo = '';

    public ?string $productionKind = null;

    protected ?string $heading = 'Radar de performance (100 = meilleure ferme de l\'axe)';

    protected ?string $maxHeight = '380px';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        if (count($this->farmIds) < FarmComparisonService::MIN_FARMS || $this->dateFrom === '' || $this->dateTo === '') {
            return ['datasets' => [], 'labels' => []];
        }

        $kind = $this->productionKind ? ProductionKind::tryFrom($this->productionKind) : null;
        $service = app(FarmComparisonService::class);
        $rows = $service->compare($this->organisationId, $this->farmIds, $this->dateFrom, $this->dateTo, $kind);
        $scores = $service->scores($rows, $kind);

        return [
            'labels' => array_values($scores['axes']),
            'datasets' => $rows->values()->map(function (array $row, int $i) use ($scores): array {
                $color = FarmComparisonBarWidget::COLORS[$i % count(FarmComparisonBarWidget::COLORS)];

                return [
                    'label' => $row['farm_name'],
                    'data' => array_values($scores['scores'][$row['farm_id']] ?? []),
                    'borderColor' => $color,
                    'backgroundColor' => self::withAlpha($color, 0.15),
                    'pointBackgroundColor' => $color,
                ];
            })->all(),
        ];
    }

    protected function getType(): string
    {
        return 'radar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => ['r' => ['beginAtZero' => true, 'max' => 100, 'ticks' => ['stepSize' => 20]]],
            'plugins' => ['legend' => ['position' => 'bottom']],
        ];
    }

    private static function withAlpha(string $hex, float $alpha): string
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        return "rgba({$r}, {$g}, {$b}, {$alpha})";
    }
}
