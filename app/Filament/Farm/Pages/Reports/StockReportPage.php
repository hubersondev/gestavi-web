<?php

namespace App\Filament\Farm\Pages\Reports;

use App\Enums\StockMovementType;
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
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class StockReportPage extends Page implements HasTable
{
    use \App\Filament\Farm\Concerns\RequiresOrganisationPermission;

    protected static string $requiredPermission = 'stocks.view';

    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube-transparent';

    protected static string|UnitEnum|null $navigationGroup = 'Rapports';

    protected static ?string $navigationLabel = 'Stocks';

    protected static ?string $title = 'Rapport des stocks';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.farm.pages.reports.stock-report';

    public ?array $filters = [];

    public function mount(): void
    {
        $this->filters = [
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->toDateString(),
            'category_id' => null,
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('filters.date_from')->label('Du')->native(false)->live(),
            DatePicker::make('filters.date_to')->label('Au')->native(false)->live(),
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
                StockMovement::query()->with(['stock', 'flock'])
            )
            ->modifyQueryUsing(function (Builder $query): void {
                $tenant = Filament::getTenant();
                $from = $this->filters['date_from'] ?? now()->startOfMonth()->toDateString();
                $to = $this->filters['date_to'] ?? now()->toDateString();
                $categoryId = $this->filters['category_id'] ?? null;

                $query
                    ->whereHas('stock', function (Builder $q) use ($tenant, $categoryId): void {
                        $q->where('organisation_id', $tenant?->getKey());
                        app(CurrentFarm::class)->restrictForCurrentUser($q);
                        if ($categoryId) {
                            $q->where('stock_category_id', $categoryId);
                        }
                    })
                    ->whereBetween('movement_date', [$from, $to]);
            })
            ->defaultSort('movement_date', 'desc')
            ->columns([
                TextColumn::make('movement_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),
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
        $this->resetPage();
    }

    public function getStockLevels(): Collection
    {
        $tenant = Filament::getTenant();
        $categoryId = $this->filters['category_id'] ?? null;

        return Stock::query()
            ->with('category')
            ->where('organisation_id', $tenant?->getKey())
            ->tap(fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q))
            ->active()
            ->when($categoryId, fn (Builder $q) => $q->where('stock_category_id', $categoryId))
            ->orderByRaw('(quantity * unit_price) DESC')
            ->get();
    }

    public function getMovements(): Collection
    {
        $tenant = Filament::getTenant();
        $from = $this->filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $to = $this->filters['date_to'] ?? now()->toDateString();
        $categoryId = $this->filters['category_id'] ?? null;

        return StockMovement::query()
            ->with(['stock', 'flock'])
            ->whereHas('stock', function (Builder $q) use ($tenant, $categoryId): void {
                $q->where('organisation_id', $tenant?->getKey());
                app(CurrentFarm::class)->restrictForCurrentUser($q);
                if ($categoryId) {
                    $q->where('stock_category_id', $categoryId);
                }
            })
            ->whereBetween('movement_date', [$from, $to])
            ->orderByDesc('movement_date')
            ->limit(200)
            ->get();
    }

    public function getKpis(): array
    {
        $stocks = $this->getStockLevels();
        $movements = $this->getMovements();

        return [
            'products' => $stocks->count(),
            'total_value' => (float) $stocks->sum(fn (Stock $s) => $s->getTotalValue()),
            'low_stock' => $stocks->filter(fn (Stock $s) => $s->isLowStock())->count(),
            'entries' => (float) $movements->where('type', StockMovementType::In)->sum('quantity'),
            'exits' => (float) $movements->where('type', StockMovementType::Out)->sum('quantity'),
            'movement_count' => $movements->count(),
        ];
    }

    public function getProductSummary(): Collection
    {
        $tenant = Filament::getTenant();
        $from = $this->filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $to = $this->filters['date_to'] ?? now()->toDateString();

        return Stock::query()
            ->where('organisation_id', $tenant?->getKey())
            ->tap(fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q))
            ->active()
            ->whereHas('movements', fn ($q) => $q->whereBetween('movement_date', [$from, $to]))
            ->withSum(['movements as entries' => fn ($q) => $q->where('type', 'in')->whereBetween('movement_date', [$from, $to])], 'quantity')
            ->withSum(['movements as exits' => fn ($q) => $q->where('type', 'out')->whereBetween('movement_date', [$from, $to])], 'quantity')
            ->withCount(['movements' => fn ($q) => $q->whereBetween('movement_date', [$from, $to])])
            ->orderByDesc('movements_count')
            ->get();
    }

    public function exportExcel(): StreamedResponse
    {
        $movements = $this->getMovements();
        $filename = 'rapport-stocks-'.($this->filters['date_from'] ?? 'all').'.xlsx';

        return response()->streamDownload(function () use ($movements): void {
            $writer = new Writer;
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValues([
                'Date', 'Produit', 'Type', 'Motif', 'Quantité',
                'Unité', 'PU (XOF)', 'Total (XOF)', 'Bande', 'Référence',
            ]));

            foreach ($movements as $mvt) {
                $writer->addRow(Row::fromValues([
                    $mvt->movement_date?->format('d/m/Y') ?? '',
                    $mvt->stock?->name ?? '',
                    $mvt->type?->getLabel() ?? '',
                    $mvt->reason?->getLabel() ?? '',
                    (float) $mvt->quantity,
                    $mvt->stock?->unit ?? '',
                    (float) ($mvt->unit_price ?? 0),
                    (float) ($mvt->total_price ?? 0),
                    $mvt->flock?->code ?? '',
                    $mvt->reference ?? '',
                ]));
            }

            $writer->close();
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
