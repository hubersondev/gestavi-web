<?php

namespace App\Filament\Farm\Pages\Reports;

use App\Enums\JournalCode;
use App\Filament\Farm\Concerns\RequiresOrganisationPermission;
use App\Filament\Farm\Pages\Reports\Concerns\ExportsReport;
use App\Models\ChartAccount;
use App\Models\Farm;
use App\Models\JournalLine;
use App\Services\AccountingService;
use App\Services\CurrentFarm;
use App\Services\CurrentFiscalYear;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * États comptables SYSCOHADA : journal, grand livre, balance, compte de résultat simplifié,
 * export « journal Sage » (Date;Journal;N° pièce;Compte;Libellé;Débit;Crédit;Tiers).
 */
class AccountingReportPage extends Page implements HasTable
{
    use ExportsReport;
    use InteractsWithTable;
    use RequiresOrganisationPermission;

    protected static string $requiredPermission = 'transactions.view';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static string|UnitEnum|null $navigationGroup = 'Rapports';

    protected static ?string $navigationLabel = 'Comptabilité';

    protected static ?string $title = 'États comptables';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.farm.pages.reports.accounting-report';

    public ?array $filters = [];

    protected ?Collection $balanceCache = null;

    public function mount(): void
    {
        $this->filters = [
            ...app(CurrentFiscalYear::class)->reportPeriod(fallbackFrom: now()->startOfYear()),
            'farm_id' => null,
            'journal' => null,
            'account_code' => null,
        ];
    }

    public function reportKey(): string
    {
        return 'comptabilite';
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('filters.date_from')->label('Du')->native(false)->displayFormat('d/m/Y')->live(),
            DatePicker::make('filters.date_to')->label('Au')->native(false)->displayFormat('d/m/Y')->live(),
            Select::make('filters.farm_id')
                ->label('Ferme')
                ->options(fn (): array => app(CurrentFarm::class)
                    ->restrictForCurrentUser(Farm::query()->where('organisation_id', Filament::getTenant()?->getKey()), 'id')
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->toArray())
                ->placeholder('Toutes les fermes')
                ->live(),
            Select::make('filters.journal')
                ->label('Journal')
                ->options(JournalCode::class)
                ->placeholder('Tous')
                ->live(),
            Select::make('filters.account_code')
                ->label('Compte')
                ->options(fn (): array => ChartAccount::query()
                    ->availableTo(Filament::getTenant()?->getKey())
                    ->orderBy('code')
                    ->get()
                    ->mapWithKeys(fn (ChartAccount $a) => [$a->code => $a->code.' — '.$a->label])
                    ->all())
                ->searchable()
                ->placeholder('Tous')
                ->live(),
        ])->columns(['sm' => 2, 'lg' => 4]);
    }

    public function updatedFilters(): void
    {
        $this->balanceCache = null;
        $this->resetTable();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function period(): array
    {
        $filters = $this->normalizedFilters();

        return [Carbon::parse($filters['date_from']), Carbon::parse($filters['date_to'])];
    }

    protected function applyFilters(Builder $query): Builder
    {
        [$from, $to] = $this->period();

        $query
            ->where('journal_lines.organisation_id', Filament::getTenant()?->getKey())
            ->whereBetween('journal_lines.entry_date', [$from->toDateString(), $to->toDateString()])
            ->when($this->filters['farm_id'] ?? null, fn (Builder $q, $farmId) => $q->where('journal_lines.farm_id', $farmId))
            ->when($this->filters['account_code'] ?? null, fn (Builder $q, $code) => $q->where('journal_lines.chart_account_code', $code))
            ->when($this->filters['journal'] ?? null, fn (Builder $q, $journal) => $q->whereHas('entry', fn (Builder $e) => $e->where('journal', $journal)));

        return app(CurrentFarm::class)->restrictForCurrentUser($query, 'journal_lines.farm_id', allowOrganisationLevel: true);
    }

    /** Journal : une ligne par imputation. */
    public function table(Table $table): Table
    {
        return $table
            ->query(JournalLine::query()->with(['entry', 'farm']))
            ->modifyQueryUsing(fn (Builder $query): Builder => $this->applyFilters($query))
            ->defaultSort('journal_lines.entry_date', 'desc')
            ->columns([
                TextColumn::make('entry_date')->label('Date')->date('d/m/Y')->sortable(),
                TextColumn::make('entry.journal')->label('Journal')->badge(),
                TextColumn::make('entry.entry_number')->label('N° pièce')->searchable(),
                TextColumn::make('chart_account_code')->label('Compte')->weight('bold')->searchable(),
                TextColumn::make('chart_account_label')->label('Intitulé')->limit(40),
                TextColumn::make('label')->label('Libellé')->limit(50)->toggleable(),
                TextColumn::make('debit')->label('Débit')->numeric(0, thousandsSeparator: ' ')->placeholder('—'),
                TextColumn::make('credit')->label('Crédit')->numeric(0, thousandsSeparator: ' ')->placeholder('—'),
                TextColumn::make('third_party')->label('Tiers')->placeholder('—')->toggleable(),
                TextColumn::make('farm.name')->label('Ferme')->placeholder('Organisation')->toggleable(),
            ])
            ->headerActions([
                TableAction::make('exportExcel')
                    ->label('Export Excel (journal Sage)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn () => $this->exportExcel()),
            ])
            ->emptyStateHeading('Aucune écriture')
            ->emptyStateDescription('Aucune écriture comptable sur cette période. Utilisez « gestavi:accounting-rebuild » pour initialiser la comptabilité depuis l\'existant.')
            ->striped()
            ->paginated([25, 50, 100]);
    }

    /** Balance : totaux débit/crédit et solde par compte. */
    public function getBalance(): Collection
    {
        if ($this->balanceCache !== null) {
            return $this->balanceCache;
        }

        [$from, $to] = $this->period();

        $this->balanceCache = app(AccountingService::class)
            ->trialBalance((int) Filament::getTenant()?->getKey(), $from, $to, $this->filters['farm_id'] ?? null)
            ->map(fn ($row): array => [
                'code' => $row->chart_account_code,
                'label' => $row->chart_account_label,
                'class' => ChartAccount::classOf($row->chart_account_code),
                'debit' => (float) $row->debit,
                'credit' => (float) $row->credit,
                'balance' => (float) $row->debit - (float) $row->credit,
            ]);

        return $this->balanceCache;
    }

    /** Grand livre : lignes groupées par compte (limité aux 2 000 premières lignes). */
    public function getLedger(): Collection
    {
        return $this->applyFilters(JournalLine::query()->with('entry'))
            ->orderBy('journal_lines.chart_account_code')
            ->orderBy('journal_lines.entry_date')
            ->limit(2000)
            ->get()
            ->groupBy('chart_account_code');
    }

    /**
     * Compte de résultat simplifié : charges (classe 6) et produits (classe 7).
     *
     * @return array{charges: Collection, produits: Collection, total_charges: float, total_produits: float, resultat: float}
     */
    public function getIncomeStatement(): array
    {
        $balance = $this->getBalance();

        $charges = $balance->where('class', 6)->values();
        $produits = $balance->where('class', 7)->values();

        $totalCharges = (float) $charges->sum('balance');
        $totalProduits = (float) $produits->sum(fn (array $row): float => -$row['balance']);

        return [
            'charges' => $charges,
            'produits' => $produits,
            'total_charges' => $totalCharges,
            'total_produits' => $totalProduits,
            'resultat' => $totalProduits - $totalCharges,
        ];
    }

    /** Export « journal Sage » : Date;Journal;N° pièce;Compte;Libellé;Débit;Crédit;Tiers. */
    public function buildXlsx(): string
    {
        $lines = $this->applyFilters(JournalLine::query()->with('entry'))
            ->orderBy('journal_lines.entry_date')
            ->orderBy('journal_lines.journal_entry_id')
            ->orderBy('journal_lines.position')
            ->get();

        return $this->xlsxToString(function (callable $addRow) use ($lines): void {
            $addRow(['Date', 'Journal', 'N° pièce', 'Compte', 'Libellé', 'Débit', 'Crédit', 'Tiers']);

            foreach ($lines as $line) {
                $addRow([
                    $line->entry_date->format('d/m/Y'),
                    $line->entry?->journal?->value,
                    $line->entry?->entry_number,
                    $line->chart_account_code,
                    $line->label ?? $line->entry?->label,
                    (float) $line->debit,
                    (float) $line->credit,
                    $line->third_party ?? '',
                ]);
            }
        });
    }

    public function buildPdf(): string
    {
        throw new \LogicException('Les états comptables s\'exportent au format Excel (journal Sage).');
    }
}
