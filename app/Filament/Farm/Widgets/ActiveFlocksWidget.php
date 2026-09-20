<?php

namespace App\Filament\Farm\Widgets;

use App\Models\Flock;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ActiveFlocksWidget extends TableWidget
{
    use \App\Filament\Farm\Concerns\RequiresOrganisationPermission;

    protected static string $requiredPermission = 'flocks.view';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $tenant = Filament::getTenant();

        return $table
            ->heading('Bandes actives')
            ->query(
                Flock::query()
                    ->active()
                    ->when($tenant, fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser(
                        $q->where('flocks.organisation_id', $tenant->getKey()),
                        'flocks.farm_id',
                    ))
                    ->with(['building', 'chickenType'])
                    ->orderByDesc('start_date')
            )
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('name')
                    ->label('Nom')
                    ->limit(20),

                TextColumn::make('chickenType.name')
                    ->label('Type')
                    ->badge()
                    ->color('info'),

                TextColumn::make('breed')
                    ->label('Race')
                    ->limit(15)
                    ->placeholder('—'),

                TextColumn::make('age')
                    ->label('Âge')
                    ->state(function (Flock $record): string {
                        $days = $record->getCurrentAgeInDays();

                        if ($days === null) {
                            return '—';
                        }

                        $weeks = round($days / 7, 1);

                        return "{$days}j ({$weeks} sem)";
                    }),

                TextColumn::make('effectif')
                    ->label('Effectif')
                    ->state(fn (Flock $record): string => number_format($record->quantity_current).' / '.number_format($record->quantity_started)),

                TextColumn::make('mortality_rate')
                    ->label('Mortalité')
                    ->state(fn (Flock $record): string => number_format($record->getMortalityRate(), 1).'%')
                    ->color(fn (Flock $record): string => match (true) {
                        $record->getMortalityRate() >= 5 => 'danger',
                        $record->getMortalityRate() >= 2 => 'warning',
                        default => 'success',
                    }),

                TextColumn::make('phase')
                    ->label('Phase')
                    ->state(fn (Flock $record): string => $record->getCurrentPhase()?->name ?? '—')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('building.name')
                    ->label('Bâtiment')
                    ->limit(15)
                    ->placeholder('—'),
            ])
            ->paginated([5])
            ->defaultSort('start_date', 'desc')
            ->emptyStateHeading('Aucune bande active')
            ->emptyStateDescription('Créez une nouvelle bande pour commencer le suivi.')
            ->emptyStateIcon('heroicon-o-rectangle-stack');
    }
}
