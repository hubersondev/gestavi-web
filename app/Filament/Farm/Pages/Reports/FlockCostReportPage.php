<?php

namespace App\Filament\Farm\Pages\Reports;

use App\Enums\FlockStatus;
use App\Filament\Farm\Concerns\RequiresOrganisationPermission;
use App\Models\Farm;
use App\Models\Flock;
use App\Services\CurrentFarm;
use App\Services\FlockCostService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Coût de revient et marge par bande, consolidés par ferme.
 */
class FlockCostReportPage extends Page implements HasTable
{
    use InteractsWithTable;
    use RequiresOrganisationPermission;

    protected static string $requiredPermission = 'flocks.view';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static string|UnitEnum|null $navigationGroup = 'Rapports';

    protected static ?string $navigationLabel = 'Coût de revient';

    protected static ?string $title = 'Coût de revient par bande';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.farm.pages.reports.flock-cost-report';

    public ?array $filters = [];

    /** @var array<int, array<string, mixed>>|null */
    protected ?array $breakdownCache = null;

    public function mount(): void
    {
        $this->filters = [
            'farm_id' => null,
            'status' => null,
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('filters.farm_id')
                ->label('Ferme')
                ->options(fn (): array => app(CurrentFarm::class)
                    ->restrictForCurrentUser(Farm::query()->where('organisation_id', Filament::getTenant()?->getKey()), 'id')
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->toArray())
                ->placeholder('Toutes les fermes')
                ->live(),

            Select::make('filters.status')
                ->label('Statut')
                ->options(FlockStatus::class)
                ->placeholder('Actives et clôturées')
                ->live(),
        ])->columns(['sm' => 2, 'lg' => 4]);
    }

    public function updatedFilters(): void
    {
        $this->breakdownCache = null;
        $this->resetTable();
    }

    protected function baseQuery(): Builder
    {
        $query = Flock::query()
            ->with(['farm', 'chickenType'])
            ->where('flocks.organisation_id', Filament::getTenant()?->getKey())
            ->when($this->filters['farm_id'] ?? null, fn (Builder $q, $farmId) => $q->where('flocks.farm_id', $farmId))
            ->when(
                $this->filters['status'] ?? null,
                fn (Builder $q, $status) => $q->where('flocks.status', $status),
                fn (Builder $q) => $q->whereIn('flocks.status', [FlockStatus::Active->value, FlockStatus::Completed->value]),
            );

        return app(CurrentFarm::class)->restrictForCurrentUser($query, 'flocks.farm_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function breakdownFor(Flock $flock): array
    {
        $this->breakdownCache ??= [];

        return $this->breakdownCache[$flock->getKey()] ??= app(FlockCostService::class)->breakdown($flock);
    }

    /**
     * Consolidation par ferme sur l'ensemble des bandes filtrées.
     */
    public function getFarmComparison(): Collection
    {
        return $this->baseQuery()->get()
            ->groupBy(fn (Flock $flock) => $flock->farm?->name ?? '—')
            ->map(function (Collection $flocks, string $farmName): array {
                $breakdowns = $flocks->map(fn (Flock $flock) => $this->breakdownFor($flock));

                $cost = (float) $breakdowns->sum(fn (array $b) => $b['couts']['total']);
                $revenue = (float) $breakdowns->sum(fn (array $b) => $b['produits']['ventes']);
                $started = (int) $flocks->sum('quantity_started');

                return [
                    'ferme' => $farmName,
                    'bandes' => $flocks->count(),
                    'sujets' => $started,
                    'cout_total' => round($cost, 2),
                    'ventes' => round($revenue, 2),
                    'marge' => round($revenue - $cost, 2),
                    'taux_marge' => $revenue > 0 ? round((($revenue - $cost) / $revenue) * 100, 1) : null,
                    'cout_par_sujet' => $started > 0 ? round($cost / $started, 0) : null,
                ];
            })
            ->sortBy('ferme')
            ->values();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->baseQuery())
            ->defaultSort('start_date', 'desc')
            ->columns([
                TextColumn::make('code')->label('Bande')->weight('bold')->searchable(),
                TextColumn::make('farm.name')->label('Ferme'),
                TextColumn::make('chickenType.production_kind')->label('Orientation')->badge(),
                TextColumn::make('status')->label('Statut')->badge(),
                TextColumn::make('quantity_started')->label('Mis en place')->numeric(thousandsSeparator: ' '),
                TextColumn::make('cost_total')
                    ->label('Coût total')
                    ->state(fn (Flock $record) => $this->breakdownFor($record)['couts']['total'])
                    ->numeric(0, thousandsSeparator: ' ')
                    ->suffix(' XOF'),
                TextColumn::make('cost_feed')
                    ->label('dont aliment')
                    ->state(fn (Flock $record) => $this->breakdownFor($record)['couts']['aliment'])
                    ->numeric(0, thousandsSeparator: ' ')
                    ->toggleable(),
                TextColumn::make('revenue')
                    ->label('Ventes')
                    ->state(fn (Flock $record) => $this->breakdownFor($record)['produits']['ventes'])
                    ->numeric(0, thousandsSeparator: ' ')
                    ->suffix(' XOF'),
                TextColumn::make('margin')
                    ->label('Marge')
                    ->state(fn (Flock $record) => $this->breakdownFor($record)['marge']['brute'])
                    ->numeric(0, thousandsSeparator: ' ')
                    ->suffix(' XOF')
                    ->color(fn ($state) => $state < 0 ? 'danger' : 'success')
                    ->weight('bold'),
                TextColumn::make('cost_per_bird')
                    ->label('Coût / sujet')
                    ->state(fn (Flock $record) => $this->breakdownFor($record)['unitaires']['cout_par_sujet_mis_en_place'])
                    ->numeric(0, thousandsSeparator: ' '),
                TextColumn::make('cost_per_kg')
                    ->label('Coût / kg vif')
                    ->state(fn (Flock $record) => $this->breakdownFor($record)['unitaires']['cout_par_kg_vif'])
                    ->numeric(0, thousandsSeparator: ' ')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('cost_per_egg')
                    ->label('Coût / œuf')
                    ->state(fn (Flock $record) => $this->breakdownFor($record)['unitaires']['cout_par_oeuf'])
                    ->numeric(1, thousandsSeparator: ' ')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->emptyStateHeading('Aucune bande')
            ->emptyStateDescription('Aucune bande active ou clôturée sur ce périmètre.')
            ->striped()
            ->paginated([10, 25]);
    }
}
