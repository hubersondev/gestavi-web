<?php

namespace App\Filament\Farm\Pages\Reports;

use App\Filament\Farm\Pages\Reports\Concerns\ExportsReport;
use App\Models\DailyRecord;
use App\Models\Farm;
use App\Models\Flock;
use App\Services\CurrentFarm;
use App\Services\FlockService;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action as TableAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
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

class ProductionReportPage extends Page implements HasTable
{
    use \App\Filament\Farm\Concerns\RequiresOrganisationPermission;

    protected static string $requiredPermission = 'daily_records.view';

    use ExportsReport;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|UnitEnum|null $navigationGroup = 'Rapports';

    protected static ?string $navigationLabel = 'Production';

    protected static ?string $title = 'Rapport de production';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.farm.pages.reports.production-report';

    public ?array $filters = [];

    /** @var array<string, mixed>|null */
    protected ?array $kpisCache = null;

    protected ?Collection $farmComparisonCache = null;

    public function mount(): void
    {
        $this->filters = [
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->toDateString(),
            'farm_id' => null,
            'flock_id' => null,
        ];
    }

    public function reportKey(): string
    {
        return 'production';
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('filters.date_from')
                ->label('Du')
                ->default(now()->startOfMonth())
                ->native(false)
                ->live(),

            DatePicker::make('filters.date_to')
                ->label('Au')
                ->default(now())
                ->native(false)
                ->live(),

            Select::make('filters.farm_id')
                ->label('Ferme')
                ->options(function (): array {
                    $tenant = Filament::getTenant();

                    return Farm::query()
                        ->where('organisation_id', $tenant?->getKey())
                        ->tap(fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q, 'id'))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray();
                })
                ->placeholder('Toutes les fermes')
                ->searchable()
                ->live(),

            Select::make('filters.flock_id')
                ->label('Bande')
                ->options(function (): array {
                    $tenant = Filament::getTenant();

                    return Flock::query()
                        ->whereHas('building.farm', fn ($q) => app(CurrentFarm::class)->restrictFarmsTableForCurrentUser($q->where('organisation_id', $tenant?->getKey())))
                        ->orderByDesc('start_date')
                        ->get()
                        ->mapWithKeys(fn (Flock $f) => [$f->id => $f->code.' — '.$f->name])
                        ->toArray();
                })
                ->placeholder('Toutes les bandes')
                ->searchable()
                ->preload()
                ->live(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                DailyRecord::query()->with('flock')
            )
            ->modifyQueryUsing(fn (Builder $query): Builder => $this->applyFilters($query)->with('flock.building.farm'))
            ->defaultSort('record_date')
            ->columns([
                TextColumn::make('record_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('flock.building.farm.name')
                    ->label('Ferme')
                    ->toggleable(),
                TextColumn::make('flock.code')
                    ->label('Bande'),
                TextColumn::make('age_days')
                    ->label('Âge')
                    ->suffix('j'),
                TextColumn::make('mortality')
                    ->label('Mortalité')
                    ->numeric()
                    ->color(fn ($state): ?string => $state > 0 ? 'danger' : null),
                TextColumn::make('culls')
                    ->label('Réformes')
                    ->numeric()
                    ->toggleable(),
                TextColumn::make('birds_present')
                    ->label('Effectif')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('eggs_collected')
                    ->label('Oeufs')
                    ->numeric()
                    ->color('success'),
                TextColumn::make('average_weight_g')
                    ->label('Poids (g)')
                    ->numeric(0),
                TextColumn::make('feed_consumed_kg')
                    ->label('Aliment (kg)')
                    ->numeric(1),
                TextColumn::make('water_consumed_l')
                    ->label('Eau (L)')
                    ->numeric(1),
                TextColumn::make('temperature_min')
                    ->label('T° min')
                    ->placeholder('—'),
                TextColumn::make('temperature_max')
                    ->label('T° max')
                    ->placeholder('—'),
            ])
            ->headerActions([
                TableAction::make('exportExcel')
                    ->label('Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn () => $this->exportExcel()),
                TableAction::make('exportPdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document')
                    ->color('danger')
                    ->action(fn () => $this->exportPdf()),
            ])
            ->emptyStateHeading('Aucun enregistrement')
            ->emptyStateDescription('Aucun enregistrement sur cette période.')
            ->striped()
            ->paginated([10, 25, 50]);
    }

    public function updatedFilters(): void
    {
        $this->kpisCache = null;
        $this->farmComparisonCache = null;
        $this->resetPage();
    }

    /**
     * Requête de base filtrée (organisation, fermes accessibles, ferme choisie,
     * bande, période) : partagée par la table, les KPI, la comparaison et les exports.
     */
    public function applyFilters(Builder $query): Builder
    {
        $tenant = Filament::getTenant();
        $filters = $this->normalizedFilters();
        $farmId = $filters['farm_id'] ?? null;
        $flockId = $filters['flock_id'] ?? null;

        return $query
            ->whereHas('flock.building.farm', function (Builder $q) use ($tenant, $farmId): void {
                $q->where('organisation_id', $tenant?->getKey());
                app(CurrentFarm::class)->restrictFarmsTableForCurrentUser($q);

                if ($farmId) {
                    $q->where('farms.id', $farmId);
                }
            })
            ->when($flockId, fn (Builder $q) => $q->where('flock_id', $flockId))
            ->whereBetween('record_date', [$filters['date_from'], $filters['date_to']]);
    }

    public function getRecords(): Collection
    {
        return $this->applyFilters(DailyRecord::query())
            ->with('flock.building.farm')
            ->orderBy('record_date')
            ->get();
    }

    /**
     * KPI agrégés en SQL sur la période filtrée.
     *
     * - mortality_rate : Σ morts / effectif en début de période, l'effectif de départ
     *   étant, pour chaque bande, (effectif présent + morts + réformes) de son premier
     *   relevé de la période ;
     * - laying_rate : Σ œufs / Σ poules-jours (birds_present cumulés) × 100 ;
     * - feed_per_bird_day : Σ aliment (g) / Σ poules-jours ;
     * - gmq / ic / ipe : uniquement quand une bande est filtrée (FlockService).
     *
     * @return array<string, mixed>
     */
    public function getKpis(): array
    {
        if ($this->kpisCache !== null) {
            return $this->kpisCache;
        }

        $totals = $this->applyFilters(DailyRecord::query())
            ->selectRaw('COUNT(*) AS records_count')
            ->selectRaw('COUNT(DISTINCT flock_id) AS flocks_count')
            ->selectRaw('COALESCE(SUM(mortality), 0) AS mortality')
            ->selectRaw('COALESCE(SUM(culls), 0) AS culls')
            ->selectRaw('COALESCE(SUM(eggs_collected), 0) AS eggs')
            ->selectRaw('COALESCE(SUM(eggs_broken), 0) AS eggs_broken')
            ->selectRaw('COALESCE(SUM(feed_consumed_kg), 0) AS total_feed')
            ->selectRaw('COALESCE(SUM(water_consumed_l), 0) AS total_water')
            ->selectRaw('COALESCE(SUM(birds_present), 0) AS hen_days')
            ->selectRaw('AVG(average_weight_g) AS avg_weight')
            ->selectRaw('AVG(eggs_collected) AS avg_eggs')
            ->first();

        // Effectif en début de période : premier relevé de chaque bande sur la période
        $startingBirds = (int) $this->applyFilters(DailyRecord::query())
            ->selectRaw('DISTINCT ON (flock_id) flock_id, (COALESCE(birds_present, 0) + mortality + culls) AS start_birds')
            ->orderBy('flock_id')
            ->orderBy('record_date')
            ->get()
            ->sum('start_birds');

        $mortality = (int) $totals->mortality;
        $henDays = (int) $totals->hen_days;
        $eggs = (int) $totals->eggs;

        $kpis = [
            'days' => (int) $totals->records_count,
            'flocks' => (int) $totals->flocks_count,
            'mortality' => $mortality,
            'culls' => (int) $totals->culls,
            'starting_birds' => $startingBirds,
            'mortality_rate' => $startingBirds > 0 ? round($mortality / $startingBirds * 100, 2) : 0.0,
            'eggs' => $eggs,
            'eggs_broken' => (int) $totals->eggs_broken,
            'avg_eggs' => round((float) $totals->avg_eggs, 1),
            'laying_rate' => $henDays > 0 ? round($eggs / $henDays * 100, 2) : 0.0,
            'avg_weight' => round((float) $totals->avg_weight, 1),
            'total_feed' => round((float) $totals->total_feed, 1),
            'total_water' => round((float) $totals->total_water, 1),
            'feed_per_bird_day' => $henDays > 0 ? round((float) $totals->total_feed * 1000 / $henDays, 1) : 0.0,
            'gmq' => null,
            'ic' => null,
            'ipe' => null,
        ];

        $flockId = $this->normalizedFilters()['flock_id'] ?? null;

        if ($flockId && ($flock = Flock::query()->find($flockId))) {
            $indicators = app(FlockService::class)->calculatePerformanceIndicators($flock);
            $kpis['gmq'] = $indicators['gmq'] ?? null;
            $kpis['ic'] = $indicators['ic'] ?? null;
            $kpis['ipe'] = $indicators['ipe'] ?? null;
        }

        return $this->kpisCache = $kpis;
    }

    /**
     * Comparaison inter-fermes sur la période : une ligne par ferme, agrégée en SQL.
     *
     * @return Collection<int, object{farm_id: int, farm_name: string, flocks: int, records: int, mortality: int, culls: int, eggs: int, total_feed: float, hen_days: int, laying_rate: float, feed_per_bird_day: float}>
     */
    public function getFarmComparison(): Collection
    {
        if ($this->farmComparisonCache !== null) {
            return $this->farmComparisonCache;
        }

        $rows = $this->applyFilters(DailyRecord::query())
            ->join('flocks', 'flocks.id', '=', 'daily_records.flock_id')
            ->join('buildings', 'buildings.id', '=', 'flocks.building_id')
            ->join('farms', 'farms.id', '=', 'buildings.farm_id')
            ->selectRaw('farms.id AS farm_id, farms.name AS farm_name')
            ->selectRaw('COUNT(DISTINCT daily_records.flock_id) AS flocks')
            ->selectRaw('COUNT(*) AS records')
            ->selectRaw('COALESCE(SUM(daily_records.mortality), 0) AS mortality')
            ->selectRaw('COALESCE(SUM(daily_records.culls), 0) AS culls')
            ->selectRaw('COALESCE(SUM(daily_records.eggs_collected), 0) AS eggs')
            ->selectRaw('COALESCE(SUM(daily_records.feed_consumed_kg), 0) AS total_feed')
            ->selectRaw('COALESCE(SUM(daily_records.birds_present), 0) AS hen_days')
            ->groupBy('farms.id', 'farms.name')
            ->orderBy('farms.name')
            ->get();

        return $this->farmComparisonCache = $rows->map(function ($row): object {
            $henDays = (int) $row->hen_days;

            return (object) [
                'farm_id' => (int) $row->farm_id,
                'farm_name' => $row->farm_name,
                'flocks' => (int) $row->flocks,
                'records' => (int) $row->records,
                'mortality' => (int) $row->mortality,
                'culls' => (int) $row->culls,
                'eggs' => (int) $row->eggs,
                'total_feed' => round((float) $row->total_feed, 1),
                'hen_days' => $henDays,
                'laying_rate' => $henDays > 0 ? round((int) $row->eggs / $henDays * 100, 2) : 0.0,
                'feed_per_bird_day' => $henDays > 0 ? round((float) $row->total_feed * 1000 / $henDays, 1) : 0.0,
            ];
        });
    }

    public function buildXlsx(): string
    {
        $records = $this->getRecords();

        return $this->xlsxToString(function (callable $addRow) use ($records): void {
            $addRow([
                'Date', 'Ferme', 'Bande', 'Âge (j)', 'Mortalité', 'Réformes', 'Effectif présent',
                'Oeufs collectés', 'Oeufs cassés', 'Poids moy. (g)', 'Aliment (kg)', 'Eau (L)',
                'T° min (°C)', 'T° max (°C)', 'Humidité (%)',
            ]);

            foreach ($records as $record) {
                $addRow([
                    $record->record_date?->format('d/m/Y') ?? '',
                    $record->flock?->building?->farm?->name ?? '',
                    $record->flock?->code ?? '',
                    $record->age_days ?? '',
                    $record->mortality ?? 0,
                    $record->culls ?? 0,
                    $record->birds_present ?? '',
                    $record->eggs_collected ?? 0,
                    $record->eggs_broken ?? 0,
                    (float) ($record->average_weight_g ?? 0),
                    (float) ($record->feed_consumed_kg ?? 0),
                    (float) ($record->water_consumed_l ?? 0),
                    (float) ($record->temperature_min ?? 0),
                    (float) ($record->temperature_max ?? 0),
                    (float) ($record->humidity_percent ?? 0),
                ]);
            }
        });
    }

    public const PDF_MAX_ROWS = 2000;

    public function buildPdf(): string
    {
        $records = $this->getRecords();
        $filters = $this->normalizedFilters();
        $flock = ! empty($filters['flock_id'])
            ? $this->applyFilters(DailyRecord::query())->with('flock')->first()?->flock
            : null;

        $pdf = Pdf::loadView('pdf.production-report', [
            'organisation' => Filament::getTenant(),
            'flock' => $flock,
            'dateFrom' => $filters['date_from'],
            'dateTo' => $filters['date_to'],
            'kpis' => $this->getKpis(),
            'farmComparison' => $this->getFarmComparison(),
            'records' => $records->take(self::PDF_MAX_ROWS),
            'truncated' => $records->count() > self::PDF_MAX_ROWS,
            'maxRows' => self::PDF_MAX_ROWS,
        ])->setPaper('a4', 'landscape');

        return $pdf->output();
    }
}
