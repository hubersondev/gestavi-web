<?php

namespace App\Filament\Farm\Widgets;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ExpenseCategoriesWidget extends ChartWidget
{
    protected ?string $heading = 'Dépenses par catégorie (mois courant)';

    protected static ?int $sort = 8;

    protected ?string $maxHeight = '300px';

    private const CATEGORY_COLORS = [
        'feed' => '#f59e0b',
        'medicine' => '#ef4444',
        'veterinary' => '#ec4899',
        'fuel' => '#6b7280',
        'utilities' => '#8b5cf6',
        'rent' => '#84cc16',
        'maintenance' => '#f97316',
        'equipment' => '#3b82f6',
        'transport' => '#14b8a6',
        'services' => '#06b6d4',
        'taxes' => '#a855f7',
        'salaries' => '#0ea5e9',
        'other' => '#9ca3af',
    ];

    protected function getData(): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return ['datasets' => [], 'labels' => []];
        }

        $expenses = Expense::query()
            ->where('organisation_id', $tenant->getKey())
            ->whereMonth('expense_date', now()->month)
            ->whereYear('expense_date', now()->year)
            ->whereNot('status', 'cancelled')
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        if ($expenses->isEmpty()) {
            return ['datasets' => [], 'labels' => []];
        }

        $labels = $expenses->map(function ($e) {
            $enum = ExpenseCategory::tryFrom($e->category);

            return $enum?->getLabel() ?? ucfirst($e->category);
        })->toArray();

        $data = $expenses->map(fn ($e) => round((float) $e->total))->toArray();
        $colors = $expenses->map(fn ($e) => self::CATEGORY_COLORS[$e->category] ?? '#9ca3af')->toArray();

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderColor' => '#ffffff',
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'right',
                ],
            ],
        ];
    }
}
