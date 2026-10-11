<?php

namespace App\Filament\Farm\Pages\Reports;

use App\Enums\TransactionType;
use App\Filament\Farm\Pages\Reports\Concerns\ExportsReport;
use App\Models\Account;
use App\Models\Expense;
use App\Models\Farm;
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
use UnitEnum;

class FinancialReportPage extends Page implements HasTable
{
    use \App\Filament\Farm\Concerns\RequiresOrganisationPermission;

    protected static string $requiredPermission = 'transactions.view';

    use ExportsReport;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Rapports';

    protected static ?string $navigationLabel = 'Financier';

    protected static ?string $title = 'Rapport financier';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.farm.pages.reports.financial-report';

    public ?array $filters = [];

    /** @var array<string, mixed>|null */
    protected ?array $kpisCache = null;

    protected ?Collection $farmComparisonCache = null;

    protected ?Collection $accountBalancesCache = null;

    public function mount(): void
    {
        $this->filters = [
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->toDateString(),
            'farm_id' => null,
            'account_id' => null,
        ];
    }

    public function reportKey(): string
    {
        return 'financier';
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
                Transaction::query()->with(['account.farm', 'user'])
            )
            ->modifyQueryUsing(fn (Builder $query): Builder => $this->applyFilters($query))
            ->defaultSort('transaction_date', 'desc')
            ->columns([
                TextColumn::make('transaction_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('account.farm.name')
                    ->label('Ferme')
                    ->placeholder('Organisation')
                    ->toggleable(),
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
        $this->kpisCache = null;
        $this->farmComparisonCache = null;
        $this->accountBalancesCache = null;
        $this->resetPage();
    }

    /**
     * Comptes entrant dans le périmètre (organisation, fermes accessibles, ferme et compte filtrés).
     */
    public function scopedAccounts(): Builder
    {
        $tenant = Filament::getTenant();
        $filters = $this->normalizedFilters();

        return Account::query()
            ->where('organisation_id', $tenant?->getKey())
            ->tap(fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q))
            ->when($filters['farm_id'] ?? null, fn (Builder $q, $farmId) => $q->where($q->qualifyColumn('farm_id'), $farmId))
            ->when($filters['account_id'] ?? null, fn (Builder $q, $accountId) => $q->where('id', $accountId));
    }

    /**
     * Requête de base des transactions de la période, partagée par table, KPI et exports.
     */
    public function applyFilters(Builder $query): Builder
    {
        $filters = $this->normalizedFilters();

        return $query
            ->whereIn('account_id', $this->scopedAccounts()->select('accounts.id'))
            ->whereBetween('transaction_date', [$filters['date_from'], $filters['date_to']]);
    }

    public function getTransactions(): Collection
    {
        return $this->applyFilters(Transaction::query())
            ->with(['account.farm', 'user'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();
    }

    public function getExpensesByCategory(): Collection
    {
        $tenant = Filament::getTenant();
        $filters = $this->normalizedFilters();

        return Expense::query()
            ->where('organisation_id', $tenant?->getKey())
            ->tap(fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q))
            ->when($filters['farm_id'] ?? null, fn (Builder $q, $farmId) => $q->where($q->qualifyColumn('farm_id'), $farmId))
            ->whereBetween('expense_date', [$filters['date_from'], $filters['date_to']])
            ->whereNot('status', 'cancelled')
            ->selectRaw('category, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();
    }

    /**
     * KPI de trésorerie agrégés en SQL sur toute la période (aucune troncature) :
     * - credits / debits / net : Σ des transactions de la période ;
     * - opening_balance : Σ (solde initial + crédits − débits antérieurs à la période) des comptes ;
     * - closing_balance : opening_balance + credits − debits.
     *
     * @return array<string, mixed>
     */
    public function getKpis(): array
    {
        if ($this->kpisCache !== null) {
            return $this->kpisCache;
        }

        $credit = TransactionType::Credit->value;
        $debit = TransactionType::Debit->value;

        $totals = $this->applyFilters(Transaction::query())
            ->selectRaw("COALESCE(SUM(CASE WHEN type = '{$credit}' THEN amount ELSE 0 END), 0) AS credits")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = '{$debit}' THEN amount ELSE 0 END), 0) AS debits")
            ->selectRaw('COUNT(*) AS count')
            ->first();

        $balances = $this->getAccountBalances();

        $credits = (float) $totals->credits;
        $debits = (float) $totals->debits;

        return $this->kpisCache = [
            'credits' => $credits,
            'debits' => $debits,
            'net' => $credits - $debits,
            'count' => (int) $totals->count,
            'accounts' => $balances->count(),
            'opening_balance' => (float) $balances->sum('opening'),
            'closing_balance' => (float) $balances->sum('closing'),
        ];
    }

    /**
     * Solde d'ouverture et de clôture par compte sur la période.
     *
     * @return Collection<int, object{account_id: int, account_name: string, farm_name: string, opening: float, credits: float, debits: float, closing: float}>
     */
    public function getAccountBalances(): Collection
    {
        if ($this->accountBalancesCache !== null) {
            return $this->accountBalancesCache;
        }

        $filters = $this->normalizedFilters();
        $from = $filters['date_from'];
        $to = $filters['date_to'];
        $credit = TransactionType::Credit->value;
        $debit = TransactionType::Debit->value;

        $accounts = $this->scopedAccounts()
            ->with('farm')
            ->withSum(['transactions as credits_before' => fn (Builder $q) => $q->where('type', $credit)->where('transaction_date', '<', $from)], 'amount')
            ->withSum(['transactions as debits_before' => fn (Builder $q) => $q->where('type', $debit)->where('transaction_date', '<', $from)], 'amount')
            ->withSum(['transactions as credits_period' => fn (Builder $q) => $q->where('type', $credit)->whereBetween('transaction_date', [$from, $to])], 'amount')
            ->withSum(['transactions as debits_period' => fn (Builder $q) => $q->where('type', $debit)->whereBetween('transaction_date', [$from, $to])], 'amount')
            ->orderBy('name')
            ->get();

        return $this->accountBalancesCache = $accounts->map(function (Account $account): object {
            $opening = (float) $account->initial_balance + (float) $account->credits_before - (float) $account->debits_before;
            $credits = (float) $account->credits_period;
            $debits = (float) $account->debits_period;

            return (object) [
                'account_id' => $account->id,
                'account_name' => $account->name,
                'farm_name' => $account->farm?->name ?? 'Organisation',
                'opening' => $opening,
                'credits' => $credits,
                'debits' => $debits,
                'closing' => $opening + $credits - $debits,
            ];
        });
    }

    /**
     * Comparaison inter-fermes : flux de la période par ferme (comptes sans ferme = « Organisation »).
     *
     * @return Collection<int, object{farm_id: int|null, farm_name: string, accounts: int, credits: float, debits: float, net: float, count: int}>
     */
    public function getFarmComparison(): Collection
    {
        if ($this->farmComparisonCache !== null) {
            return $this->farmComparisonCache;
        }

        $credit = TransactionType::Credit->value;
        $debit = TransactionType::Debit->value;

        $rows = $this->applyFilters(Transaction::query())
            ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->leftJoin('farms', 'farms.id', '=', 'accounts.farm_id')
            ->selectRaw('accounts.farm_id AS farm_id, farms.name AS farm_name')
            ->selectRaw('COUNT(DISTINCT transactions.account_id) AS accounts')
            ->selectRaw("COALESCE(SUM(CASE WHEN transactions.type = '{$credit}' THEN transactions.amount ELSE 0 END), 0) AS credits")
            ->selectRaw("COALESCE(SUM(CASE WHEN transactions.type = '{$debit}' THEN transactions.amount ELSE 0 END), 0) AS debits")
            ->selectRaw('COUNT(*) AS count')
            ->groupBy('accounts.farm_id', 'farms.name')
            ->orderByRaw('farms.name NULLS FIRST')
            ->get();

        return $this->farmComparisonCache = $rows->map(fn ($row): object => (object) [
            'farm_id' => $row->farm_id !== null ? (int) $row->farm_id : null,
            'farm_name' => $row->farm_name ?? 'Organisation',
            'accounts' => (int) $row->accounts,
            'credits' => (float) $row->credits,
            'debits' => (float) $row->debits,
            'net' => (float) $row->credits - (float) $row->debits,
            'count' => (int) $row->count,
        ]);
    }

    public function buildXlsx(): string
    {
        $transactions = $this->getTransactions();
        $balances = $this->getAccountBalances();

        return $this->xlsxToString(function (callable $addRow) use ($transactions, $balances): void {
            $addRow(['Soldes par compte']);
            $addRow(['Ferme', 'Compte', 'Solde ouverture (XOF)', 'Entrées (XOF)', 'Sorties (XOF)', 'Solde clôture (XOF)']);

            foreach ($balances as $balance) {
                $addRow([$balance->farm_name, $balance->account_name, $balance->opening, $balance->credits, $balance->debits, $balance->closing]);
            }

            $addRow([]);
            $addRow(['Détail des transactions']);
            $addRow([
                'Date', 'Ferme', 'Compte', 'Type', 'Catégorie', 'Montant (XOF)',
                'Solde avant', 'Solde après', 'Description', 'Rapproché',
            ]);

            foreach ($transactions as $tx) {
                $addRow([
                    $tx->transaction_date?->format('d/m/Y') ?? '',
                    $tx->account?->farm?->name ?? 'Organisation',
                    $tx->account?->name ?? '',
                    $tx->type?->getLabel() ?? '',
                    $tx->category?->getLabel() ?? '',
                    (float) $tx->amount,
                    (float) $tx->balance_before,
                    (float) $tx->balance_after,
                    $tx->description ?? '',
                    $tx->is_reconciled ? 'Oui' : 'Non',
                ]);
            }
        });
    }

    public const PDF_MAX_ROWS = 2000;

    public function buildPdf(): string
    {
        $transactions = $this->getTransactions();
        $filters = $this->normalizedFilters();

        $pdf = Pdf::loadView('pdf.financial-report', [
            'organisation' => Filament::getTenant(),
            'dateFrom' => $filters['date_from'],
            'dateTo' => $filters['date_to'],
            'kpis' => $this->getKpis(),
            'accountBalances' => $this->getAccountBalances(),
            'farmComparison' => $this->getFarmComparison(),
            'transactions' => $transactions->take(self::PDF_MAX_ROWS),
            'truncated' => $transactions->count() > self::PDF_MAX_ROWS,
            'maxRows' => self::PDF_MAX_ROWS,
            'expensesByCategory' => $this->getExpensesByCategory(),
        ])->setPaper('a4', 'landscape');

        return $pdf->output();
    }
}
