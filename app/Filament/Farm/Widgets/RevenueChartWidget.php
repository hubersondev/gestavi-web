<?php

namespace App\Filament\Farm\Widgets;

use App\Enums\SaleStatus;
use App\Models\Expense;
use App\Models\Sale;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class RevenueChartWidget extends ChartWidget
{
    use \App\Filament\Farm\Concerns\RequiresOrganisationPermission;

    protected static string $requiredPermission = 'sales.view';

    protected ?string $heading = 'Chiffre d\'affaires & Dépenses (6 mois)';

    protected static ?int $sort = 3;

    protected ?string $maxHeight = '300px';

    private const FRENCH_MONTHS = [
        'Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun',
        'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc',
    ];

    protected function getData(): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return ['datasets' => [], 'labels' => []];
        }

        $orgId = $tenant->getKey();

        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $months->push(now()->subMonths($i)->startOfMonth());
        }

        $from = $months->first()->toDateString();
        $to = now()->endOfMonth()->toDateString();

        // Deux requêtes agrégées par mois (ventes, dépenses) au lieu de 12
        $salesByMonth = Sale::query()
            ->where('organisation_id', $orgId)
            ->tap(fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q))
            ->whereBetween('sale_date', [$from, $to])
            ->whereNot('status', SaleStatus::Cancelled)
            ->selectRaw("to_char(sale_date, 'YYYY-MM') AS ym, COALESCE(SUM(total), 0) AS total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $expensesByMonth = Expense::query()
            ->where('organisation_id', $orgId)
            ->tap(fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q))
            ->whereBetween('expense_date', [$from, $to])
            ->whereNot('status', 'cancelled')
            ->selectRaw("to_char(expense_date, 'YYYY-MM') AS ym, COALESCE(SUM(amount), 0) AS total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $revenues = $months->map(fn ($month) => (float) ($salesByMonth[$month->format('Y-m')] ?? 0))->toArray();
        $expenses = $months->map(fn ($month) => (float) ($expensesByMonth[$month->format('Y-m')] ?? 0))->toArray();

        $labels = $months->map(fn ($m) => self::FRENCH_MONTHS[$m->month - 1].' '.$m->format('y'))->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Chiffre d\'affaires',
                    'data' => $revenues,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Dépenses',
                    'data' => $expenses,
                    'borderColor' => '#ef4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
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
