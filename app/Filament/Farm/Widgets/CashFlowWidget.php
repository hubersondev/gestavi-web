<?php

namespace App\Filament\Farm\Widgets;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class CashFlowWidget extends ChartWidget
{
    use \App\Filament\Farm\Concerns\RequiresOrganisationPermission;

    protected static string $requiredPermission = 'transactions.view';

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
            ->tap(fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q))
            ->where('is_active', true)
            ->pluck('id');

        if ($accountIds->isEmpty()) {
            return ['datasets' => [], 'labels' => []];
        }

        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $months->push(now()->subMonths($i)->startOfMonth());
        }

        // Une seule requête agrégée par mois et par type, au lieu de 12 requêtes
        $credit = TransactionType::Credit->value;
        $debit = TransactionType::Debit->value;

        $totals = Transaction::query()
            ->whereIn('account_id', $accountIds)
            ->whereBetween('transaction_date', [$months->first()->toDateString(), now()->endOfMonth()->toDateString()])
            ->selectRaw("to_char(transaction_date, 'YYYY-MM') AS ym")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = '{$credit}' THEN amount ELSE 0 END), 0) AS credits")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = '{$debit}' THEN amount ELSE 0 END), 0) AS debits")
            ->groupBy('ym')
            ->get()
            ->keyBy('ym');

        $labels = [];
        $credits = [];
        $debits = [];

        foreach ($months as $month) {
            $key = $month->format('Y-m');
            $labels[] = self::FRENCH_MONTHS[$month->month].' '.$month->format('y');
            $credits[] = (int) ($totals->get($key)?->credits ?? 0);
            $debits[] = (int) ($totals->get($key)?->debits ?? 0);
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
