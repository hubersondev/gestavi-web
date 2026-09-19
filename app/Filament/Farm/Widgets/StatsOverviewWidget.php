<?php

namespace App\Filament\Farm\Widgets;

use App\Enums\SaleStatus;
use App\Models\Account;
use App\Models\DailyRecord;
use App\Models\Flock;
use App\Models\Sale;
use App\Models\Stock;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class StatsOverviewWidget extends BaseWidget
{
    use \App\Filament\Farm\Concerns\RequiresOrganisationPermission;

    protected static string $requiredPermission = 'flocks.view';

    protected static ?int $sort = 1;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return [];
        }

        $orgId = $tenant->getKey();
        $user = Filament::auth()->user();
        $can = fn (string $permission): bool => $user?->hasOrganisationPermission($tenant, $permission) ?? false;

        // Les tuiles financières et stock ne sont affichées qu'aux membres autorisés
        return array_values(array_filter([
            $this->getActiveFlocksStat($orgId),
            $this->getBirdCountStat($orgId),
            $this->getProductionStat($orgId),
            $this->getMortalityStat($orgId),
            $can('sales.view') ? $this->getMonthlyRevenueStat($orgId) : null,
            $can('accounts.view') ? $this->getAccountBalanceStat($orgId) : null,
            $can('sales.view') ? $this->getUnpaidInvoicesStat($orgId) : null,
            $can('stocks.view') ? $this->getLowStockStat($orgId) : null,
        ]));
    }

    private function getActiveFlocksStat(int $orgId): Stat
    {
        $flocks = Flock::query()
            ->active()
            ->whereHas('building.farm', fn (Builder $q) => app(CurrentFarm::class)->restrictFarmsTableForCurrentUser($q->where('organisation_id', $orgId)))
            ->with('chickenType')
            ->get();

        $count = $flocks->count();
        $types = $flocks->groupBy(fn (Flock $f) => $f->chickenType?->name ?? 'Autre')
            ->map->count()
            ->map(fn (int $c, string $name) => "{$c} {$name}")
            ->values()
            ->implode(' · ');

        return Stat::make('Bandes actives', $count)
            ->description($types ?: 'Aucune bande active')
            ->descriptionIcon('heroicon-o-rectangle-stack')
            ->color($count > 0 ? 'success' : 'gray');
    }

    private function getBirdCountStat(int $orgId): Stat
    {
        $totalBirds = (int) Flock::query()
            ->active()
            ->whereHas('building.farm', fn (Builder $q) => app(CurrentFarm::class)->restrictFarmsTableForCurrentUser($q->where('organisation_id', $orgId)))
            ->sum('quantity_current');

        $totalStarted = (int) Flock::query()
            ->active()
            ->whereHas('building.farm', fn (Builder $q) => app(CurrentFarm::class)->restrictFarmsTableForCurrentUser($q->where('organisation_id', $orgId)))
            ->sum('quantity_started');

        $survivalRate = $totalStarted > 0
            ? round(($totalBirds / $totalStarted) * 100, 1)
            : 0;

        return Stat::make('Effectif volailles', number_format($totalBirds))
            ->description("Survie : {$survivalRate}% ({$totalStarted} départ)")
            ->descriptionIcon('heroicon-o-heart')
            ->color($survivalRate >= 90 ? 'success' : ($survivalRate >= 80 ? 'warning' : 'danger'));
    }

    private function getProductionStat(int $orgId): Stat
    {
        $activeFlockIds = Flock::query()
            ->active()
            ->whereHas('building.farm', fn (Builder $q) => app(CurrentFarm::class)->restrictFarmsTableForCurrentUser($q->where('organisation_id', $orgId)))
            ->pluck('id');

        if ($activeFlockIds->isEmpty()) {
            return Stat::make('Production', '-')
                ->description('Aucune bande active')
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color('gray');
        }

        $latestRecords = DailyRecord::query()
            ->whereIn('flock_id', $activeFlockIds)
            ->where('record_date', '>=', now()->subDays(3))
            ->orderByDesc('record_date')
            ->get();

        $lastDate = $latestRecords->first()?->record_date;

        if (! $lastDate) {
            return Stat::make('Production', '-')
                ->description('Aucun relevé récent')
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color('gray');
        }

        $dayRecords = $latestRecords->where('record_date', $lastDate);
        $totalEggs = $dayRecords->sum('eggs_collected');
        $avgWeight = $dayRecords->avg('average_weight_g');
        $dayLabel = $lastDate->format('d/m');

        $weekEggs = DailyRecord::query()
            ->whereIn('flock_id', $activeFlockIds)
            ->where('record_date', '>=', now()->subDays(7))
            ->selectRaw('record_date, SUM(eggs_collected) as total')
            ->groupBy('record_date')
            ->orderBy('record_date')
            ->pluck('total')
            ->toArray();

        if ($totalEggs > 0) {
            return Stat::make('Production oeufs', number_format($totalEggs))
                ->description("Relevé du {$dayLabel}")
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color('success')
                ->chart($weekEggs ?: null);
        }

        if ($avgWeight) {
            return Stat::make('Poids moyen', number_format($avgWeight / 1000, 2).' kg')
                ->description("Relevé du {$dayLabel}")
                ->descriptionIcon('heroicon-o-scale')
                ->color('success');
        }

        return Stat::make('Production', '-')
            ->description('Aucune donnée')
            ->descriptionIcon('heroicon-o-chart-bar')
            ->color('gray');
    }

    private function getMortalityStat(int $orgId): Stat
    {
        $activeFlockIds = Flock::query()
            ->active()
            ->whereHas('building.farm', fn (Builder $q) => app(CurrentFarm::class)->restrictFarmsTableForCurrentUser($q->where('organisation_id', $orgId)))
            ->pluck('id');

        if ($activeFlockIds->isEmpty()) {
            return Stat::make('Mortalité 7j', '-')
                ->description('Aucune bande active')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('gray');
        }

        $weekMortality = DailyRecord::query()
            ->whereIn('flock_id', $activeFlockIds)
            ->where('record_date', '>=', now()->subDays(7))
            ->selectRaw('record_date, SUM(mortality) as total')
            ->groupBy('record_date')
            ->orderBy('record_date')
            ->get();

        $totalMortality = (int) $weekMortality->sum('total');
        $totalBirds = (int) Flock::query()->whereIn('id', $activeFlockIds)->sum('quantity_started');
        $mortalityRate = $totalBirds > 0 ? ($totalMortality / $totalBirds) * 100 : 0;

        $color = match (true) {
            $mortalityRate >= 5 => 'danger',
            $mortalityRate >= 2 => 'warning',
            default => 'success',
        };

        return Stat::make('Mortalité 7j', number_format($mortalityRate, 2).'%')
            ->description("{$totalMortality} mort(s) sur 7 jours")
            ->descriptionIcon('heroicon-o-exclamation-triangle')
            ->color($color)
            ->chart($weekMortality->pluck('total')->toArray() ?: null);
    }

    private function getMonthlyRevenueStat(int $orgId): Stat
    {
        $thisMonth = (float) Sale::query()
            ->where('organisation_id', $orgId)
            ->tap(fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q))
            ->whereMonth('sale_date', now()->month)
            ->whereYear('sale_date', now()->year)
            ->whereNot('status', SaleStatus::Cancelled)
            ->sum('total');

        $lastMonth = (float) Sale::query()
            ->where('organisation_id', $orgId)
            ->tap(fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q))
            ->whereMonth('sale_date', now()->subMonth()->month)
            ->whereYear('sale_date', now()->subMonth()->year)
            ->whereNot('status', SaleStatus::Cancelled)
            ->sum('total');

        $trend = $lastMonth > 0
            ? (int) round((($thisMonth - $lastMonth) / $lastMonth) * 100)
            : null;

        $trendDescription = match (true) {
            $trend === null => 'Pas de comparaison',
            $trend > 0 => "+{$trend}% vs mois dernier",
            $trend < 0 => "{$trend}% vs mois dernier",
            default => 'Stable vs mois dernier',
        };

        $trendIcon = match (true) {
            $trend > 0 => 'heroicon-o-arrow-trending-up',
            $trend !== null && $trend < 0 => 'heroicon-o-arrow-trending-down',
            default => 'heroicon-o-minus',
        };

        return Stat::make('CA mensuel', $this->formatXOF($thisMonth))
            ->description($trendDescription)
            ->descriptionIcon($trendIcon)
            ->color($trend !== null && $trend >= 0 ? 'success' : 'danger');
    }

    private function getAccountBalanceStat(int $orgId): Stat
    {
        $accounts = Account::query()
            ->where('organisation_id', $orgId)
            ->tap(fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q))
            ->where('is_active', true)
            ->get();

        $totalBalance = (float) $accounts->sum('current_balance');
        $accountCount = $accounts->count();

        $color = match (true) {
            $totalBalance < 0 => 'danger',
            $totalBalance < 50000 => 'warning',
            default => 'success',
        };

        return Stat::make('Solde comptes', $this->formatXOF($totalBalance))
            ->description("{$accountCount} compte(s) actif(s)")
            ->descriptionIcon('heroicon-o-banknotes')
            ->color($color);
    }

    private function getUnpaidInvoicesStat(int $orgId): Stat
    {
        $unpaid = Sale::query()
            ->where('organisation_id', $orgId)
            ->tap(fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q))
            ->whereIn('status', [SaleStatus::Pending, SaleStatus::Partial])
            ->get();

        $unpaidCount = $unpaid->count();
        $unpaidAmount = (float) $unpaid->sum('balance');
        $overdue = $unpaid->filter(fn (Sale $s) => $s->due_date?->isPast())->count();

        $description = $overdue > 0
            ? "{$overdue} en retard · ".$this->formatXOF($unpaidAmount)
            : $this->formatXOF($unpaidAmount).' à encaisser';

        $color = match (true) {
            $overdue > 0 => 'danger',
            $unpaidCount > 0 => 'warning',
            default => 'success',
        };

        return Stat::make('Factures impayées', $unpaidCount)
            ->description($description)
            ->descriptionIcon('heroicon-o-document-text')
            ->color($color);
    }

    private function getLowStockStat(int $orgId): Stat
    {
        $lowStockCount = Stock::query()
            ->where('organisation_id', $orgId)
            ->tap(fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q))
            ->active()
            ->lowStock()
            ->count();

        return Stat::make('Stocks bas', $lowStockCount)
            ->description($lowStockCount > 0 ? 'Réapprovisionnement à prévoir' : 'Tout est au-dessus du seuil')
            ->descriptionIcon($lowStockCount > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
            ->color($lowStockCount > 0 ? 'danger' : 'success');
    }

    private function formatXOF(float $amount): string
    {
        if (abs($amount) >= 1_000_000) {
            return number_format($amount / 1_000_000, 1).'M XOF';
        }

        if (abs($amount) >= 1_000) {
            return number_format($amount / 1_000, 0).'K XOF';
        }

        return number_format($amount, 0).' XOF';
    }
}
