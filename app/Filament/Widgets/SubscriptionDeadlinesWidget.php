<?php

namespace App\Filament\Widgets;

use App\Enums\OrganisationStatus;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Abonnements qui réclament une action : échéance sous 7 jours, ou expirés en délai de grâce
 * (l'organisation passera en lecture seule sans renouvellement).
 */
class SubscriptionDeadlinesWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Abonnements à traiter';

    public function table(Table $table): Table
    {
        $grace = SubscriptionService::graceDays();

        return $table
            ->query(
                Subscription::query()
                    ->with(['organisation'])
                    ->where(fn (Builder $q) => $q
                        ->where(fn (Builder $soon) => $soon->expiringWithin(7))
                        ->orWhere(fn (Builder $expired) => $expired
                            ->where('status', 'expired')
                            ->whereDate('ends_at', '>=', today()->subDays($grace)->toDateString())
                            ->whereHas('organisation', fn (Builder $o) => $o->where('status', OrganisationStatus::Active->value))))
                    ->orderBy('ends_at')
            )
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('organisation.name')->label('Structure')->weight('medium'),
                TextColumn::make('plan_name')->label('Plan'),
                TextColumn::make('status')->label('Statut')->badge(),
                TextColumn::make('ends_at')
                    ->label('Échéance')
                    ->date('d/m/Y')
                    ->description(fn (Subscription $record): string => $record->isCurrent()
                        ? 'dans '.$record->daysRemaining().' jour(s)'
                        : 'lecture seule le '.$record->ends_at->toImmutable()->addDays($grace + 1)->format('d/m/Y'))
                    ->color(fn (Subscription $record): string => $record->isCurrent() ? 'warning' : 'danger'),
                TextColumn::make('price')->label('Prix')->money('XOF', locale: 'fr')->alignRight(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Ouvrir')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Subscription $record): string => SubscriptionResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading('Aucun abonnement à traiter')
            ->emptyStateDescription('Aucune échéance sous 7 jours et aucune organisation en délai de grâce.');
    }
}
