<?php

namespace App\Filament\Farm\Widgets;

use App\Models\Stock;
use App\Models\StockLot;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class StockStatsOverviewWidget extends StatsOverviewWidget
{
    use \App\Filament\Farm\Concerns\RequiresOrganisationPermission;

    protected static string $requiredPermission = 'stocks.view';

    protected static ?int $sort = 9;

    protected function getStats(): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return [];
        }

        $stocksBaseQuery = Stock::query()
            ->where('organisation_id', $tenant->getKey())
            ->tap(fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q))
            ->active();

        $activeStocks = (clone $stocksBaseQuery)->count();
        $lowStockCount = (clone $stocksBaseQuery)->lowStock()->count();
        $totalValue = (float) (clone $stocksBaseQuery)
            ->selectRaw('COALESCE(SUM(quantity * unit_price), 0) as total')
            ->value('total');

        $lotsBaseQuery = StockLot::query()
            ->whereHas('stock', fn ($q) => app(CurrentFarm::class)->restrictForCurrentUser($q->where('organisation_id', $tenant->getKey())));

        $activeLots = (clone $lotsBaseQuery)->where('current_quantity', '>', 0)->count();
        $expiringSoon = (clone $lotsBaseQuery)->expiringSoon(7)->where('current_quantity', '>', 0)->count();

        return [
            Stat::make('Produits actifs', $activeStocks)
                ->description('En stock')
                ->descriptionIcon('heroicon-m-cube-transparent')
                ->color('primary'),

            Stat::make('Valeur totale', number_format($totalValue, 0, ',', ' ').' XOF')
                ->description('Quantité × PU moyen')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Stocks sous seuil', $lowStockCount)
                ->description($lowStockCount > 0 ? 'Réapprovisionnement à prévoir' : 'Tout est au-dessus du seuil')
                ->descriptionIcon($lowStockCount > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($lowStockCount > 0 ? 'danger' : 'success'),

            Stat::make('Lots actifs', $activeLots)
                ->description($expiringSoon > 0 ? $expiringSoon.' périment sous 7j' : 'Aucun lot critique')
                ->descriptionIcon($expiringSoon > 0 ? 'heroicon-m-clock' : 'heroicon-m-check-circle')
                ->color($expiringSoon > 0 ? 'warning' : 'info'),
        ];
    }
}
