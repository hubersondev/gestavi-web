<?php

namespace App\Filament\Farm\Pages\Reports;

use App\Enums\StockMovementType;
use App\Filament\Farm\Pages\Reports\Concerns\ExportsReport;
use App\Models\Farm;
use App\Models\Stock;
use App\Models\StockCategory;
use App\Models\StockMovement;
use App\Services\CurrentFarm;
use BackedEnum;
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

class StockReportPage extends Page implements HasTable
{
    use \App\Filament\Farm\Concerns\RequiresOrganisationPermission;

    protected static string $requiredPermission = 'stocks.view';

    use ExportsReport;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube-transparent';

    protected static string|UnitEnum|null $navigationGroup = 'Rapports';

    protected static ?string $navigationLabel = 'Stocks';

    protected static ?string $title = 'Rapport des stocks';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.farm.pages.reports.stock-report';

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
            'category_id' => null,
        ];
    }

    public function reportKey(): string
    {
        return 'stocks';
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('filters.date_from')->label('Du')->native(false)->live(),
            DatePicker::make('filters.date_to')->label('Au')->native(false)->live(),
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
            Select::make('filters.category_id')
                ->label('Catégorie')
                ->options(function (): array {
                    $tenant = Filament::getTenant();

                    return StockCategory::query()
                        ->where('organisation_id', $tenant?->getKey())
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray();
                })
                ->placeholder('Toutes')
                ->searchable()
                ->live(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                StockMovement::query()->with(['stock.farm', 'flock'])
            )
            ->modifyQueryUsing(fn (Builder $query): Builder => $this->applyFilters($query))
            ->defaultSort('movement_date', 'desc')
            ->columns([
                TextColumn::make('movement_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('stock.farm.name')
                    ->label('Ferme')
                    ->placeholder('Organisation')
                    ->toggleable(),
                TextColumn::make('stock.name')
                    ->label('Produit')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($state) => $state === StockMovementType::In ? 'success' : 'danger'),
                TextColumn::make('reason')
                    ->label('Motif')
                    ->color('gray'),
                TextColumn::make('quantity')
                    ->label('Quantité')
                    ->numeric(0)
                    ->color(fn ($record) => $record->type === StockMovementType::In ? 'success' : 'danger'),
                TextColumn::make('total_price')
                    ->label('Valeur (XOF)')
                    ->numeric(0),
            ])
            ->headerActions([
                TableAction::make('exportExcel')
                    ->label('Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn () => $this->exportExcel()),
            ])
            ->emptyStateHeading('Aucun mouvement')
            ->emptyStateDescription('Aucun mouvement sur cette période.')
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
     * Produits du périmètre : organisation, fermes accessibles, ferme et catégorie filtrées.
     */
    public function scopedStocks(): Builder
    {
        $tenant = Filament::getTenant();
        $filters = $this->normalizedFilters();

        return Stock::query()
            ->where('stocks.organisation_id', $tenant?->getKey())
            ->tap(fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q, 'stocks.farm_id'))
            ->when($filters['farm_id'] ?? null, fn (Builder $q, $farmId) => $q->where('stocks.farm_id', $farmId))
            ->when($filters['category_id'] ?? null, fn (Builder $q, $categoryId) => $q->where('stocks.stock_category_id', $categoryId));
    }

    /**
     * Requête de base des mouvements de la période, partagée par la table, les KPI et les exports.
     */
    public function applyFilters(Builder $query): Builder
    {
        $filters = $this->normalizedFilters();

        return $query
            ->whereIn('stock_movements.stock_id', $this->scopedStocks()->select('stocks.id'))
            ->whereBetween('stock_movements.movement_date', [$filters['date_from'], $filters['date_to']]);
    }

    public function getStockLevels(): Collection
    {
        return $this->scopedStocks()
            ->with(['category', 'farm'])
            ->where('stocks.is_active', true)
            ->orderByRaw('(quantity * unit_price) DESC')
            ->get();
    }

    public function getMovements(): Collection
    {
        return $this->applyFilters(StockMovement::query())
            ->with(['stock.farm', 'flock'])
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * KPI stocks agrégés en SQL :
     * - products / total_value / low_stock : état courant des produits actifs du périmètre
     *   (valeur = Σ quantité × prix unitaire ; sous seuil = quantité ≤ seuil > 0) ;
     * - entries / exits / movement_count : mouvements de la période, sans troncature.
     *
     * @return array<string, mixed>
     */
    public function getKpis(): array
    {
        if ($this->kpisCache !== null) {
            return $this->kpisCache;
        }

        $levels = $this->scopedStocks()
            ->where('stocks.is_active', true)
            ->selectRaw('COUNT(*) AS products')
            ->selectRaw('COALESCE(SUM(quantity * unit_price), 0) AS total_value')
            ->selectRaw('COUNT(*) FILTER (WHERE quantity <= min_quantity AND min_quantity > 0) AS low_stock')
            ->first();

        $in = StockMovementType::In->value;
        $out = StockMovementType::Out->value;

        $movements = $this->applyFilters(StockMovement::query())
            ->selectRaw("COALESCE(SUM(CASE WHEN type = '{$in}' THEN quantity ELSE 0 END), 0) AS entries")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = '{$out}' THEN quantity ELSE 0 END), 0) AS exits")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = '{$in}' THEN total_price ELSE 0 END), 0) AS entries_value")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = '{$out}' THEN total_price ELSE 0 END), 0) AS exits_value")
            ->selectRaw('COUNT(*) AS movement_count')
            ->first();

        return $this->kpisCache = [
            'products' => (int) $levels->products,
            'total_value' => (float) $levels->total_value,
            'low_stock' => (int) $levels->low_stock,
            'entries' => (float) $movements->entries,
            'exits' => (float) $movements->exits,
            'entries_value' => (float) $movements->entries_value,
            'exits_value' => (float) $movements->exits_value,
            'movement_count' => (int) $movements->movement_count,
        ];
    }

    /**
     * Comparaison inter-fermes : valeur de stock et mouvements de la période par ferme.
     *
     * @return Collection<int, object{farm_id: int|null, farm_name: string, products: int, total_value: float, low_stock: int, entries: float, exits: float, movement_count: int}>
     */
    public function getFarmComparison(): Collection
    {
        if ($this->farmComparisonCache !== null) {
            return $this->farmComparisonCache;
        }

        $levels = $this->scopedStocks()
            ->where('stocks.is_active', true)
            ->leftJoin('farms', 'farms.id', '=', 'stocks.farm_id')
            ->selectRaw('stocks.farm_id AS farm_id, farms.name AS farm_name')
            ->selectRaw('COUNT(*) AS products')
            ->selectRaw('COALESCE(SUM(stocks.quantity * stocks.unit_price), 0) AS total_value')
            ->selectRaw('COUNT(*) FILTER (WHERE stocks.quantity <= stocks.min_quantity AND stocks.min_quantity > 0) AS low_stock')
            ->groupBy('stocks.farm_id', 'farms.name')
            ->get()
            ->keyBy(fn ($row) => $row->farm_id ?? 0);

        $in = StockMovementType::In->value;
        $out = StockMovementType::Out->value;

        $movements = $this->applyFilters(StockMovement::query())
            ->join('stocks', 'stocks.id', '=', 'stock_movements.stock_id')
            ->selectRaw('stocks.farm_id AS farm_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN stock_movements.type = '{$in}' THEN stock_movements.quantity ELSE 0 END), 0) AS entries")
            ->selectRaw("COALESCE(SUM(CASE WHEN stock_movements.type = '{$out}' THEN stock_movements.quantity ELSE 0 END), 0) AS exits")
            ->selectRaw('COUNT(*) AS movement_count')
            ->groupBy('stocks.farm_id')
            ->get()
            ->keyBy(fn ($row) => $row->farm_id ?? 0);

        $farmIds = $levels->keys()->merge($movements->keys())->unique()->sort()->values();

        return $this->farmComparisonCache = $farmIds->map(function ($key) use ($levels, $movements): object {
            $level = $levels->get($key);
            $movement = $movements->get($key);

            return (object) [
                'farm_id' => $key === 0 ? null : (int) $key,
                'farm_name' => $level?->farm_name ?? ($key === 0 ? 'Organisation' : Farm::query()->find($key)?->name ?? '—'),
                'products' => (int) ($level?->products ?? 0),
                'total_value' => (float) ($level?->total_value ?? 0),
                'low_stock' => (int) ($level?->low_stock ?? 0),
                'entries' => (float) ($movement?->entries ?? 0),
                'exits' => (float) ($movement?->exits ?? 0),
                'movement_count' => (int) ($movement?->movement_count ?? 0),
            ];
        })->values();
    }

    public function getProductSummary(): Collection
    {
        $filters = $this->normalizedFilters();
        $from = $filters['date_from'];
        $to = $filters['date_to'];

        return $this->scopedStocks()
            ->where('stocks.is_active', true)
            ->whereHas('movements', fn ($q) => $q->whereBetween('movement_date', [$from, $to]))
            ->withSum(['movements as entries' => fn ($q) => $q->where('type', 'in')->whereBetween('movement_date', [$from, $to])], 'quantity')
            ->withSum(['movements as exits' => fn ($q) => $q->where('type', 'out')->whereBetween('movement_date', [$from, $to])], 'quantity')
            ->withCount(['movements' => fn ($q) => $q->whereBetween('movement_date', [$from, $to])])
            ->orderByDesc('movements_count')
            ->get();
    }

    public function buildXlsx(): string
    {
        $movements = $this->getMovements();
        $levels = $this->getStockLevels();

        return $this->xlsxToString(function (callable $addRow) use ($movements, $levels): void {
            $addRow(['Niveaux de stock']);
            $addRow(['Ferme', 'Produit', 'Catégorie', 'Quantité', 'Unité', 'Seuil', 'PU (XOF)', 'Valeur (XOF)']);

            foreach ($levels as $stock) {
                $addRow([
                    $stock->farm?->name ?? 'Organisation',
                    $stock->name,
                    $stock->category?->name ?? '',
                    (float) $stock->quantity,
                    $stock->unit,
                    (float) $stock->min_quantity,
                    (float) $stock->unit_price,
                    (float) $stock->getTotalValue(),
                ]);
            }

            $addRow([]);
            $addRow(['Mouvements de la période']);
            $addRow([
                'Date', 'Ferme', 'Produit', 'Type', 'Motif', 'Quantité',
                'Unité', 'PU (XOF)', 'Total (XOF)', 'Bande', 'Référence',
            ]);

            foreach ($movements as $mvt) {
                $addRow([
                    $mvt->movement_date?->format('d/m/Y') ?? '',
                    $mvt->stock?->farm?->name ?? 'Organisation',
                    $mvt->stock?->name ?? '',
                    $mvt->type?->getLabel() ?? '',
                    $mvt->reason?->getLabel() ?? '',
                    (float) $mvt->quantity,
                    $mvt->stock?->unit ?? '',
                    (float) ($mvt->unit_price ?? 0),
                    (float) ($mvt->total_price ?? 0),
                    $mvt->flock?->code ?? '',
                    $mvt->reference ?? '',
                ]);
            }
        });
    }
}
