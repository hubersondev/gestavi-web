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
            $months->push(now()->subMonths($i));
        }

        $revenues = $months->map(fn ($month) => (float) Sale::query()
            ->where('organisation_id', $orgId)
            ->tap(fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q))
            ->whereMonth('sale_date', $month->month)
            ->whereYear('sale_date', $month->year)
            ->whereNot('status', SaleStatus::Cancelled)
            ->sum('total')
        )->toArray();

        $expenses = $months->map(fn ($month) => (float) Expense::query()
            ->where('organisation_id', $orgId)
            ->tap(fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q))
            ->whereMonth('expense_date', $month->month)
            ->whereYear('expense_date', $month->year)
            ->whereNot('status', 'cancelled')
            ->sum('amount')
        )->toArray();

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
