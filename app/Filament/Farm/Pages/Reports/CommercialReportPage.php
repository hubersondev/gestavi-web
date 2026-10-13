<?php

namespace App\Filament\Farm\Pages\Reports;

use App\Enums\CreditNoteStatus;
use App\Enums\SaleStatus;
use App\Filament\Farm\Pages\Reports\Concerns\ExportsReport;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Farm;
use App\Models\Sale;
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

class CommercialReportPage extends Page implements HasTable
{
    use \App\Filament\Farm\Concerns\RequiresOrganisationPermission;

    protected static string $requiredPermission = 'sales.view';

    use ExportsReport;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static string|UnitEnum|null $navigationGroup = 'Rapports';

    protected static ?string $navigationLabel = 'Commercial';

    protected static ?string $title = 'Rapport commercial';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.farm.pages.reports.commercial-report';

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
        ];
    }

    public function reportKey(): string
    {
        return 'commercial';
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
        ])->columns(['sm' => 2, 'lg' => 4]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Sale::query()->with(['customer', 'farm'])
            )
            ->modifyQueryUsing(fn (Builder $query): Builder => $this->applyFilters($query))
            ->defaultSort('sale_date', 'desc')
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('N°')
                    ->searchable(),
                TextColumn::make('sale_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('farm.name')
                    ->label('Ferme')
                    ->placeholder('Organisation')
                    ->toggleable(),
                TextColumn::make('customer.name')
                    ->label('Client')
                    ->default('Comptant'),
                TextColumn::make('total')
                    ->label('Total (XOF)')
                    ->numeric(0)
                    ->sortable(),
                TextColumn::make('paid')
                    ->label('Payé (XOF)')
                    ->numeric(0)
                    ->color('success'),
                TextColumn::make('balance')
                    ->label('Dû (XOF)')
                    ->numeric(0)
                    ->color(fn ($state): ?string => (float) $state > 0 ? 'danger' : null),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),
            ])
            ->headerActions([
                TableAction::make('exportExcel')
                    ->label('Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn () => $this->exportExcel()),
            ])
            ->emptyStateHeading('Aucune vente')
            ->emptyStateDescription('Aucune vente sur cette période.')
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
     * Requête de base des ventes non annulées de la période (organisation, fermes
     * accessibles, ferme filtrée), partagée par la table, les KPI et les exports.
     */
    public function applyFilters(Builder $query): Builder
    {
        $tenant = Filament::getTenant();
        $filters = $this->normalizedFilters();

        return $query
            ->where('sales.organisation_id', $tenant?->getKey())
            ->tap(fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q, 'sales.farm_id'))
            ->when($filters['farm_id'] ?? null, fn (Builder $q, $farmId) => $q->where('sales.farm_id', $farmId))
            ->whereBetween('sales.sale_date', [$filters['date_from'], $filters['date_to']])
            ->whereNot('sales.status', SaleStatus::Cancelled);
    }

    public function getSales(): Collection
    {
        return $this->applyFilters(Sale::query())
            ->with(['customer', 'farm'])
            ->orderByDesc('sale_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * KPI commerciaux agrégés en SQL :
     * - total_ca : Σ total TTC ; ca_ht : Σ (total − TVA) ;
     * - total_paid / total_due : Σ payé / Σ reste dû ;
     * - avg_basket : moyenne du total TTC ;
     * - overdue : factures échues (due_date < aujourd'hui) encore dues ;
     * - credit_notes_applied : Σ des avoirs imputés (applied_at dans la période) ;
     * - net_ca : total_ca − credit_notes_applied.
     *
     * @return array<string, mixed>
     */
    public function getKpis(): array
    {
        if ($this->kpisCache !== null) {
            return $this->kpisCache;
        }

        $pending = SaleStatus::Pending->value;
        $partial = SaleStatus::Partial->value;
        $today = now()->toDateString();

        $totals = $this->applyFilters(Sale::query())
            ->selectRaw('COUNT(*) AS count')
            ->selectRaw('COALESCE(SUM(total), 0) AS total_ca')
            ->selectRaw('COALESCE(SUM(total - tax_amount), 0) AS ca_ht')
            ->selectRaw('COALESCE(SUM(tax_amount), 0) AS tax')
            ->selectRaw('COALESCE(SUM(paid), 0) AS total_paid')
            ->selectRaw('COALESCE(SUM(balance), 0) AS total_due')
            ->selectRaw('COALESCE(AVG(total), 0) AS avg_basket')
            ->selectRaw("COUNT(*) FILTER (WHERE due_date IS NOT NULL AND due_date < '{$today}' AND status IN ('{$pending}', '{$partial}')) AS overdue")
            ->first();

        $filters = $this->normalizedFilters();

        $creditNotesApplied = (float) CreditNote::query()
            ->where('status', CreditNoteStatus::Applied->value)
            ->whereBetween('applied_at', [$filters['date_from'].' 00:00:00', $filters['date_to'].' 23:59:59'])
            ->whereIn('sale_id', $this->applyFilters(Sale::query())->select('sales.id'))
            ->sum('amount');

        return $this->kpisCache = [
            'total_ca' => (float) $totals->total_ca,
            'ca_ht' => (float) $totals->ca_ht,
            'tax' => (float) $totals->tax,
            'total_paid' => (float) $totals->total_paid,
            'total_due' => (float) $totals->total_due,
            'count' => (int) $totals->count,
            'avg_basket' => (float) $totals->avg_basket,
            'overdue' => (int) $totals->overdue,
            'credit_notes_applied' => $creditNotesApplied,
            'net_ca' => (float) $totals->total_ca - $creditNotesApplied,
        ];
    }

    /**
     * Comparaison inter-fermes : ventes de la période par ferme (ventes sans ferme = « Organisation »).
     *
     * @return Collection<int, object{farm_id: int|null, farm_name: string, count: int, total_ca: float, total_paid: float, total_due: float, avg_basket: float}>
     */
    public function getFarmComparison(): Collection
    {
        if ($this->farmComparisonCache !== null) {
            return $this->farmComparisonCache;
        }

        $rows = $this->applyFilters(Sale::query())
            ->leftJoin('farms', 'farms.id', '=', 'sales.farm_id')
            ->selectRaw('sales.farm_id AS farm_id, farms.name AS farm_name')
            ->selectRaw('COUNT(*) AS count')
            ->selectRaw('COALESCE(SUM(sales.total), 0) AS total_ca')
            ->selectRaw('COALESCE(SUM(sales.paid), 0) AS total_paid')
            ->selectRaw('COALESCE(SUM(sales.balance), 0) AS total_due')
            ->selectRaw('COALESCE(AVG(sales.total), 0) AS avg_basket')
            ->groupBy('sales.farm_id', 'farms.name')
            ->orderByRaw('farms.name NULLS FIRST')
            ->get();

        return $this->farmComparisonCache = $rows->map(fn ($row): object => (object) [
            'farm_id' => $row->farm_id !== null ? (int) $row->farm_id : null,
            'farm_name' => $row->farm_name ?? 'Organisation',
            'count' => (int) $row->count,
            'total_ca' => (float) $row->total_ca,
            'total_paid' => (float) $row->total_paid,
            'total_due' => (float) $row->total_due,
            'avg_basket' => (float) $row->avg_basket,
        ]);
    }

    public function getTopCustomers(): Collection
    {
        $tenant = Filament::getTenant();
        $filters = $this->normalizedFilters();
        $from = $filters['date_from'];
        $to = $filters['date_to'];
        $farmId = $filters['farm_id'] ?? null;

        $salesConstraint = fn (Builder $q) => $q
            ->whereBetween('sale_date', [$from, $to])
            ->when($farmId, fn (Builder $s) => $s->where($s->qualifyColumn('farm_id'), $farmId))
            ->whereNot('status', SaleStatus::Cancelled);

        return Customer::query()
            ->where('organisation_id', $tenant?->getKey())
            ->tap(fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q))
            ->whereHas('sales', $salesConstraint)
            ->withCount(['sales' => $salesConstraint])
            ->withSum(['sales' => $salesConstraint], 'total')
            ->orderByDesc('sales_sum_total')
            ->limit(10)
            ->get();
    }

    public function buildXlsx(): string
    {
        $sales = $this->getSales();

        return $this->xlsxToString(function (callable $addRow) use ($sales): void {
            $addRow([
                'N° Facture', 'Date', 'Ferme', 'Client', 'Sous-total (XOF)',
                'Remise', 'TVA', 'Total (XOF)', 'Payé (XOF)',
                'Reste dû (XOF)', 'Statut',
            ]);

            foreach ($sales as $sale) {
                $addRow([
                    $sale->invoice_number ?? '',
                    $sale->sale_date?->format('d/m/Y') ?? '',
                    $sale->farm?->name ?? 'Organisation',
                    $sale->customer?->name ?? 'Comptant',
                    (float) $sale->subtotal,
                    (float) $sale->discount_amount,
                    (float) $sale->tax_amount,
                    (float) $sale->total,
                    (float) $sale->paid,
                    (float) $sale->balance,
                    $sale->status?->getLabel() ?? '',
                ]);
            }
        });
    }
}
