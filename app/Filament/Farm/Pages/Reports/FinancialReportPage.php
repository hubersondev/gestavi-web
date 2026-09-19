<?php

namespace App\Filament\Farm\Pages\Reports;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Expense;
use App\Models\Transaction;
use App\Services\CurrentFarm;
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
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class FinancialReportPage extends Page implements HasTable
{
    use \App\Filament\Farm\Concerns\RequiresOrganisationPermission;

    protected static string $requiredPermission = 'transactions.view';

    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Rapports';

    protected static ?string $navigationLabel = 'Financier';

    protected static ?string $title = 'Rapport financier';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.farm.pages.reports.financial-report';

    public ?array $filters = [];

    public function mount(): void
    {
        $this->filters = [
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->toDateString(),
            'account_id' => null,
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('filters.date_from')->label('Du')->native(false)->live(),
            DatePicker::make('filters.date_to')->label('Au')->native(false)->live(),
            Select::make('filters.account_id')
                ->label('Compte')
                ->options(function (): array {
                    $tenant = Filament::getTenant();

                    return Account::query()
                        ->where('organisation_id', $tenant?->getKey())
                        ->tap(fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray();
                })
                ->placeholder('Tous les comptes')
                ->searchable()
                ->live(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Transaction::query()->with(['account', 'user'])
            )
            ->modifyQueryUsing(function (Builder $query): void {
                $tenant = Filament::getTenant();
                $from = $this->filters['date_from'] ?? now()->startOfMonth()->toDateString();
                $to = $this->filters['date_to'] ?? now()->toDateString();
                $accountId = $this->filters['account_id'] ?? null;

                $query
                    ->whereHas('account', fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q->where('organisation_id', $tenant?->getKey())))
                    ->when($accountId, fn (Builder $q) => $q->where('account_id', $accountId))
                    ->whereBetween('transaction_date', [$from, $to]);
            })
            ->defaultSort('transaction_date', 'desc')
            ->columns([
                TextColumn::make('transaction_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('account.name')
                    ->label('Compte'),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($state) => $state === TransactionType::Credit ? 'success' : 'danger'),
                TextColumn::make('category')
                    ->label('Catégorie')
                    ->color('gray'),
                TextColumn::make('amount')
                    ->label('Montant (XOF)')
                    ->numeric(0)
                    ->color(fn ($record) => $record->type === TransactionType::Credit ? 'success' : 'danger')
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->color('gray'),
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
            ->emptyStateHeading('Aucune transaction')
            ->emptyStateDescription('Aucune transaction sur cette période.')
            ->striped()
            ->paginated([10, 25, 50]);
    }

    public function updatedFilters(): void
    {
        $this->resetPage();
    }

    public function getTransactions(): Collection
    {
        $tenant = Filament::getTenant();
        $from = $this->filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $to = $this->filters['date_to'] ?? now()->toDateString();
        $accountId = $this->filters['account_id'] ?? null;

        return Transaction::query()
            ->with(['account', 'user'])
            ->whereHas('account', fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q->where('organisation_id', $tenant?->getKey())))
            ->when($accountId, fn (Builder $q) => $q->where('account_id', $accountId))
            ->whereBetween('transaction_date', [$from, $to])
            ->orderByDesc('transaction_date')
            ->limit(200)
            ->get();
    }

    public function getExpensesByCategory(): Collection
    {
        $tenant = Filament::getTenant();
        $from = $this->filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $to = $this->filters['date_to'] ?? now()->toDateString();

        return Expense::query()
            ->where('organisation_id', $tenant?->getKey())
            ->tap(fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q))
            ->whereBetween('expense_date', [$from, $to])
            ->whereNot('status', 'cancelled')
            ->selectRaw('category, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();
    }

    public function getKpis(): array
    {
        $transactions = $this->getTransactions();

        $credits = (float) $transactions->where('type', TransactionType::Credit)->sum('amount');
        $debits = (float) $transactions->where('type', TransactionType::Debit)->sum('amount');

        return [
            'credits' => $credits,
            'debits' => $debits,
            'net' => $credits - $debits,
            'count' => $transactions->count(),
        ];
    }

    public function exportExcel(): StreamedResponse
    {
        $transactions = $this->getTransactions();
        $filename = 'rapport-financier-'.($this->filters['date_from'] ?? 'all').'.xlsx';

        return response()->streamDownload(function () use ($transactions): void {
            $writer = new Writer;
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValues([
                'Date', 'Compte', 'Type', 'Catégorie', 'Montant (XOF)',
                'Solde avant', 'Solde après', 'Description', 'Rapproché',
            ]));

            foreach ($transactions as $tx) {
                $writer->addRow(Row::fromValues([
                    $tx->transaction_date?->format('d/m/Y') ?? '',
                    $tx->account?->name ?? '',
                    $tx->type?->getLabel() ?? '',
                    $tx->category?->getLabel() ?? '',
                    (float) $tx->amount,
                    (float) $tx->balance_before,
                    (float) $tx->balance_after,
                    $tx->description ?? '',
                    $tx->is_reconciled ? 'Oui' : 'Non',
                ]));
            }

            $writer->close();
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function exportPdf(): StreamedResponse
    {
        $transactions = $this->getTransactions();
        $kpis = $this->getKpis();
        $expensesByCategory = $this->getExpensesByCategory();
        $organisation = Filament::getTenant();

        $pdf = Pdf::loadView('pdf.financial-report', [
            'organisation' => $organisation,
            'dateFrom' => $this->filters['date_from'],
            'dateTo' => $this->filters['date_to'],
            'kpis' => $kpis,
            'transactions' => $transactions->take(100),
            'expensesByCategory' => $expensesByCategory,
        ])->setPaper('a4', 'landscape');

        $filename = 'rapport-financier-'.($this->filters['date_from'] ?? 'all').'.pdf';

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }
}
