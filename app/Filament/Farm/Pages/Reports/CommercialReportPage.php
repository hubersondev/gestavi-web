<?php

namespace App\Filament\Farm\Pages\Reports;

use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Sale;
use BackedEnum;
use Filament\Actions\Action as TableAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
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

class CommercialReportPage extends Page implements HasTable
{
    use \App\Filament\Farm\Concerns\RequiresOrganisationPermission;

    protected static string $requiredPermission = 'sales.view';

    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static string|UnitEnum|null $navigationGroup = 'Rapports';

    protected static ?string $navigationLabel = 'Commercial';

    protected static ?string $title = 'Rapport commercial';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.farm.pages.reports.commercial-report';

    public ?array $filters = [];

    public function mount(): void
    {
        $this->filters = [
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->toDateString(),
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('filters.date_from')->label('Du')->native(false)->live(),
            DatePicker::make('filters.date_to')->label('Au')->native(false)->live(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Sale::query()->with('customer')
            )
            ->modifyQueryUsing(function (Builder $query): void {
                $tenant = Filament::getTenant();
                $from = $this->filters['date_from'] ?? now()->startOfMonth()->toDateString();
                $to = $this->filters['date_to'] ?? now()->toDateString();

                $query
                    ->where('organisation_id', $tenant?->getKey())
                    ->whereBetween('sale_date', [$from, $to])
                    ->whereNot('status', SaleStatus::Cancelled);
            })
            ->defaultSort('sale_date', 'desc')
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('N°')
                    ->searchable(),
                TextColumn::make('sale_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),
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
        $this->resetPage();
    }

    public function getSales(): Collection
    {
        $tenant = Filament::getTenant();
        $from = $this->filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $to = $this->filters['date_to'] ?? now()->toDateString();

        return Sale::query()
            ->with('customer')
            ->where('organisation_id', $tenant?->getKey())
            ->whereBetween('sale_date', [$from, $to])
            ->whereNot('status', SaleStatus::Cancelled)
            ->orderByDesc('sale_date')
            ->get();
    }

    public function getKpis(): array
    {
        $sales = $this->getSales();

        return [
            'total_ca' => (float) $sales->sum('total'),
            'total_paid' => (float) $sales->sum('paid'),
            'total_due' => (float) $sales->sum('balance'),
            'count' => $sales->count(),
            'avg_basket' => $sales->count() > 0 ? (float) $sales->avg('total') : 0,
            'overdue' => $sales->filter(fn (Sale $s) => $s->isOverdue())->count(),
        ];
    }

    public function getTopCustomers(): Collection
    {
        $tenant = Filament::getTenant();
        $from = $this->filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $to = $this->filters['date_to'] ?? now()->toDateString();

        return Customer::query()
            ->where('organisation_id', $tenant?->getKey())
            ->whereHas('sales', fn (Builder $q) => $q
                ->whereBetween('sale_date', [$from, $to])
                ->whereNot('status', SaleStatus::Cancelled))
            ->withCount(['sales' => fn (Builder $q) => $q
                ->whereBetween('sale_date', [$from, $to])
                ->whereNot('status', SaleStatus::Cancelled)])
            ->withSum(['sales' => fn (Builder $q) => $q
                ->whereBetween('sale_date', [$from, $to])
                ->whereNot('status', SaleStatus::Cancelled)], 'total')
            ->orderByDesc('sales_sum_total')
            ->limit(10)
            ->get();
    }

    public function exportExcel(): StreamedResponse
    {
        $sales = $this->getSales();
        $filename = 'rapport-commercial-'.($this->filters['date_from'] ?? 'all').'.xlsx';

        return response()->streamDownload(function () use ($sales): void {
            $writer = new Writer;
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValues([
                'N° Facture', 'Date', 'Client', 'Sous-total (XOF)',
                'Remise', 'TVA', 'Total (XOF)', 'Payé (XOF)',
                'Reste dû (XOF)', 'Statut',
            ]));

            foreach ($sales as $sale) {
                $writer->addRow(Row::fromValues([
                    $sale->invoice_number ?? '',
                    $sale->sale_date?->format('d/m/Y') ?? '',
                    $sale->customer?->name ?? 'Comptant',
                    (float) $sale->subtotal,
                    (float) $sale->discount_amount,
                    (float) $sale->tax_amount,
                    (float) $sale->total,
                    (float) $sale->paid,
                    (float) $sale->balance,
                    $sale->status?->getLabel() ?? '',
                ]));
            }

            $writer->close();
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
