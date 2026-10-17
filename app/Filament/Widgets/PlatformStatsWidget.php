<?php

namespace App\Filament\Widgets;

use App\Enums\OrganisationStatus;
use App\Enums\UserType;
use App\Models\Organisation;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Indicateurs de la plateforme : organisations par statut, abonnements en cours et à échéance,
 * revenu d'abonnement du mois, utilisateurs ferme, nouvelles organisations.
 */
class PlatformStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $byStatus = Organisation::query()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $active = (int) ($byStatus[OrganisationStatus::Active->value] ?? 0);
        $readOnly = (int) ($byStatus[OrganisationStatus::ReadOnly->value] ?? 0);
        $suspended = (int) ($byStatus[OrganisationStatus::Suspended->value] ?? 0);
        $createdThisMonth = Organisation::query()->where('created_at', '>=', now()->startOfMonth())->count();

        $current = Subscription::query()->current()->count();
        $expiring = Subscription::query()->expiringWithin(7)->count();
        $inGrace = Subscription::query()
            ->where('status', 'expired')
            ->whereDate('ends_at', '>=', today()->subDays(SubscriptionService::graceDays()))
            ->whereHas('organisation', fn ($q) => $q->where('status', OrganisationStatus::Active->value))
            ->count();

        $revenue = (float) Subscription::query()
            ->whereIn('status', ['active', 'cancelled', 'expired'])
            ->where('starts_at', '>=', today()->startOfMonth()->toDateString())
            ->sum('price');
        $revenuePrevious = (float) Subscription::query()
            ->whereIn('status', ['active', 'cancelled', 'expired'])
            ->whereBetween('starts_at', [today()->subMonthNoOverflow()->startOfMonth()->toDateString(), today()->subMonthNoOverflow()->endOfMonth()->toDateString()])
            ->sum('price');

        $farmUsers = User::query()->where('type', UserType::Farmer)->where('is_active', true)->count();
        $withDevices = User::query()->where('type', UserType::Farmer)->whereHas('tokens')->count();

        return [
            Stat::make('Organisations actives', number_format($active, 0, ',', ' '))
                ->description(($readOnly > 0 ? $readOnly.' en lecture seule · ' : '').($suspended > 0 ? $suspended.' suspendue(s) · ' : '').'+'.$createdThisMonth.' ce mois')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color($readOnly + $suspended > 0 ? 'warning' : 'success'),

            Stat::make('Abonnements en cours', number_format($current, 0, ',', ' '))
                ->description($expiring.' à échéance sous 7 jours · '.$inGrace.' en délai de grâce')
                ->descriptionIcon($expiring + $inGrace > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($expiring + $inGrace > 0 ? 'warning' : 'success'),

            Stat::make('Abonnements encaissés ce mois', number_format($revenue, 0, ',', ' ').' XOF')
                ->description('Mois précédent : '.number_format($revenuePrevious, 0, ',', ' ').' XOF')
                ->descriptionIcon($revenue >= $revenuePrevious ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($revenue >= $revenuePrevious ? 'success' : 'gray'),

            Stat::make('Utilisateurs ferme actifs', number_format($farmUsers, 0, ',', ' '))
                ->description($withDevices.' avec l\'application mobile')
                ->descriptionIcon('heroicon-m-device-phone-mobile')
                ->color('info'),
        ];
    }
}
