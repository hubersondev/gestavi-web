<?php

namespace App\Filament\Farm\Widgets;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class CashFlowWidget extends ChartWidget
{
    protected ?string $heading = 'Flux de trésorerie (6 mois)';

    protected static ?int $sort = 7;

    protected ?string $maxHeight = '300px';

    private const FRENCH_MONTHS = [
        1 => 'Jan', 2 => 'Fév', 3 => 'Mar', 4 => 'Avr',
        5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Aoû',
        9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Déc',
    ];

    protected function getData(): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return ['datasets' => [], 'labels' => []];
        }

        $accountIds = Account::query()
            ->where('organisation_id', $tenant->getKey())
            ->where('is_active', true)
            ->pluck('id');

        if ($accountIds->isEmpty()) {
            return ['datasets' => [], 'labels' => []];
        }

        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $months->push(now()->subMonths($i));
        }

        $labels = [];
        $credits = [];
        $debits = [];

        foreach ($months as $month) {
            $labels[] = self::FRENCH_MONTHS[$month->month] . ' ' . $month->format('y');

            $credits[] = (int) Transaction::query()
                ->whereIn('account_id', $accountIds)
                ->where('type', TransactionType::Credit)
                ->whereYear('transaction_date', $month->year)
                ->whereMonth('transaction_date', $month->month)
                ->sum('amount');

            $debits[] = (int) Transaction::query()
                ->whereIn('account_id', $accountIds)
                ->where('type', TransactionType::Debit)
                ->whereYear('transaction_date', $month->year)
                ->whereMonth('transaction_date', $month->month)
                ->sum('amount');
        }

        return [
            'datasets' => [
                [
                    'label' => 'Entrées',
                    'data' => $credits,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.7)',
                    'borderColor' => '#10b981',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'Sorties',
                    'data' => $debits,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.7)',
                    'borderColor' => '#ef4444',
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
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
