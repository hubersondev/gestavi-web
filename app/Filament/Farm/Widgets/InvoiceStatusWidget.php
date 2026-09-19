<?php

namespace App\Filament\Farm\Widgets;

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class InvoiceStatusWidget extends BaseWidget
{
    use \App\Filament\Farm\Concerns\RequiresOrganisationPermission;

    protected static string $requiredPermission = 'sales.view';

    protected static ?int $sort = 6;

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

        $sales = Sale::query()
            ->where('organisation_id', $orgId)
            ->tap(fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q))
            ->whereNot('status', SaleStatus::Cancelled)
            ->get();

        if ($sales->isEmpty()) {
            return [
                Stat::make('Factures', 0)
                    ->description('Aucune facture')
                    ->descriptionIcon('heroicon-o-document-text')
                    ->color('gray'),
            ];
        }

        return [
            $this->getTotalInvoicesStat($sales),
            $this->getPaidStat($sales),
            $this->getPendingStat($sales),
            $this->getOverdueStat($orgId),
        ];
    }

    private function getTotalInvoicesStat($sales): Stat
    {
        $thisMonth = $sales->filter(fn (Sale $s) => $s->sale_date?->isCurrentMonth());
        $totalMonth = (float) $thisMonth->sum('total');

        return Stat::make('Factures du mois', $thisMonth->count())
            ->description($this->formatXOF($totalMonth))
            ->descriptionIcon('heroicon-o-document-text')
            ->color('primary');
    }

    private function getPaidStat($sales): Stat
    {
        $paid = $sales->where('status', SaleStatus::Paid);
        $paidAmount = (float) $paid->sum('total');
        $paidCount = $paid->count();
        $total = $sales->count();
        $percent = $total > 0 ? round(($paidCount / $total) * 100) : 0;

        return Stat::make('Payées', $paidCount)
            ->description("{$percent}% · ".$this->formatXOF($paidAmount))
            ->descriptionIcon('heroicon-o-check-circle')
            ->color('success');
    }

    private function getPendingStat($sales): Stat
    {
        $pending = $sales->whereIn('status', [SaleStatus::Pending, SaleStatus::Partial]);
        $pendingBalance = (float) $pending->sum('balance');
        $partialCount = $pending->where('status', SaleStatus::Partial)->count();
        $pendingCount = $pending->where('status', SaleStatus::Pending)->count();

        $details = collect([
            $pendingCount > 0 ? "{$pendingCount} en attente" : null,
            $partialCount > 0 ? "{$partialCount} partielle(s)" : null,
        ])->filter()->implode(' · ');

        return Stat::make('Impayées', $pending->count())
            ->description(($details ?: 'Aucune').' · '.$this->formatXOF($pendingBalance))
            ->descriptionIcon('heroicon-o-clock')
            ->color($pending->count() > 0 ? 'warning' : 'success');
    }

    private function getOverdueStat(int $orgId): Stat
    {
        $overdue = Sale::query()
            ->where('organisation_id', $orgId)
            ->tap(fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q))
            ->whereIn('status', [SaleStatus::Pending, SaleStatus::Partial])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->get();

        $overdueBalance = (float) $overdue->sum('balance');
        $overdueCount = $overdue->count();

        $recent = $overdue->filter(fn (Sale $s) => $s->due_date->diffInDays(now()) <= 7)->count();
        $medium = $overdue->filter(fn (Sale $s) => $s->due_date->diffInDays(now()) > 7 && $s->due_date->diffInDays(now()) <= 30)->count();
        $old = $overdue->filter(fn (Sale $s) => $s->due_date->diffInDays(now()) > 30)->count();

        $details = collect([
            $recent > 0 ? "{$recent} <7j" : null,
            $medium > 0 ? "{$medium} 7-30j" : null,
            $old > 0 ? "{$old} >30j" : null,
        ])->filter()->implode(' · ');

        return Stat::make('En retard', $overdueCount)
            ->description(($details ?: 'Aucune').' · '.$this->formatXOF($overdueBalance))
            ->descriptionIcon('heroicon-o-exclamation-triangle')
            ->color($overdueCount > 0 ? 'danger' : 'success');
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
