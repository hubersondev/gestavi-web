<?php

namespace App\Filament\Farm\Widgets;

use App\Enums\VaccinationStatus;
use App\Models\Flock;
use App\Models\Vaccination;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class VaccinationCalendarWidget extends TableWidget
{
    use \App\Filament\Farm\Concerns\RequiresOrganisationPermission;

    protected static string $requiredPermission = 'vaccinations.view';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $tenant = Filament::getTenant();

        $activeFlockIds = $tenant
            ? Flock::query()
                ->active()
                ->where('flocks.organisation_id', $tenant->getKey())->tap(fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q, 'flocks.farm_id'))
                ->pluck('id')
            : collect();

        return $table
            ->heading('Vaccinations à venir & retards')
            ->query(
                Vaccination::query()
                    ->whereIn('flock_id', $activeFlockIds)
                    ->where(function (Builder $q): void {
                        $q->where(function (Builder $sub): void {
                            $sub->where('status', VaccinationStatus::Pending)
                                ->where('scheduled_date', '<', now());
                        })->orWhere(function (Builder $sub): void {
                            $sub->where('status', VaccinationStatus::Pending)
                                ->whereBetween('scheduled_date', [now(), now()->addDays(7)]);
                        })->orWhere('status', VaccinationStatus::Delayed);
                    })
                    ->with(['flock', 'healthProgram'])
                    ->orderBy('scheduled_date')
            )
            ->columns([
                TextColumn::make('flock.code')
                    ->label('Bande')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('treatment_name')
                    ->label('Traitement')
                    ->searchable()
                    ->limit(25),

                TextColumn::make('treatment_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'vaccin' => 'success',
                        'antiparasitaire' => 'warning',
                        'antibiotique' => 'info',
                        'vitamine' => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => ucfirst($state ?? '')),

                TextColumn::make('scheduled_date')
                    ->label('Date prévue')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('urgency')
                    ->label('Statut')
                    ->state(function (Vaccination $record): string {
                        if ($record->isOverdue()) {
                            $days = $record->getDaysOverdue();

                            return "En retard ({$days}j)";
                        }

                        $days = $record->getDaysUntilScheduled();

                        if ($days === 0) {
                            return "Aujourd'hui";
                        }

                        return "Dans {$days}j";
                    })
                    ->badge()
                    ->color(function (Vaccination $record): string {
                        if ($record->isOverdue()) {
                            return 'danger';
                        }

                        $days = $record->getDaysUntilScheduled();

                        return $days !== null && $days <= 1 ? 'warning' : 'success';
                    }),

                TextColumn::make('administration_route')
                    ->label('Voie')
                    ->formatStateUsing(fn (?string $state): string => $state ? ucfirst($state) : '—'),
            ])
            ->paginated([5])
            ->defaultSort('scheduled_date')
            ->emptyStateHeading('Aucune vaccination prévue')
            ->emptyStateDescription('Pas de vaccination à venir dans les 7 prochains jours.')
            ->emptyStateIcon('heroicon-o-shield-check');
    }
}
