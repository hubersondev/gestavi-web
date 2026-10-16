<?php

namespace App\Filament\Farm\Pages\Reports;

use App\Enums\EquipmentMovementType;
use App\Enums\StockMovementReason;
use App\Enums\StockMovementType;
use App\Enums\TransactionCategory;
use App\Enums\TransactionType;
use App\Filament\Farm\Concerns\RequiresOrganisationPermission;
use App\Filament\Farm\Pages\Reports\Concerns\ExportsReport;
use App\Models\DailyRecord;
use App\Models\EquipmentMovement;
use App\Models\Farm;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Services\CurrentFarm;
use App\Services\CurrentFiscalYear;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Rapport « Pertes et vols » : sur une période et un périmètre de fermes, ce qui a disparu —
 * sujets (mortalité, réformes, vols), stocks (pertes, casse, vols, valorisés), matériel volé,
 * vols de caisse — en quantités et en valeur, avec la part des vols isolée.
 */
class LossesReportPage extends Page
{
    use ExportsReport;
    use RequiresOrganisationPermission;

    protected static string $requiredPermission = 'daily_records.view';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static string|UnitEnum|null $navigationGroup = 'Rapports';

    protected static ?string $navigationLabel = 'Pertes et vols';

    protected static ?string $title = 'Rapport des pertes et vols';

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.farm.pages.reports.losses-report';

    public ?array $filters = [];

    /** @var array<string, mixed>|null */
    protected ?array $kpisCache = null;

    public function mount(): void
    {
        $this->filters = [
            ...app(CurrentFiscalYear::class)->reportPeriod(),
            'farm_id' => null,
        ];
    }

    public function reportKey(): string
    {
        return 'pertes-vols';
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

    public function updatedFilters(): void
    {
        $this->kpisCache = null;
    }

    /**
     * Périmètre commun : organisation, fermes accessibles, ferme filtrée, période.
     */
    private function scope(Builder $query, string $farmColumn, string $dateColumn): Builder
    {
        $tenant = Filament::getTenant();
        $filters = $this->normalizedFilters();
        $table = $query->getModel()->getTable();

        return $query
            ->where("{$table}.organisation_id", $tenant?->getKey())
            ->tap(fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q, $farmColumn))
            ->when($filters['farm_id'] ?? null, fn (Builder $q, $farmId) => $q->where($farmColumn, $farmId))
            // Bornes par jour : les mouvements d'équipement portent une date-heure
            ->whereDate($dateColumn, '>=', $filters['date_from'])
            ->whereDate($dateColumn, '<=', $filters['date_to']);
    }

    /** Relevés de la période (sujets). */
    public function recordsQuery(): Builder
    {
        return $this->scope(DailyRecord::query(), 'daily_records.farm_id', 'daily_records.record_date');
    }

    /** Sorties de stock pour perte, casse ou vol. */
    public function stockLossesQuery(): Builder
    {
        return $this->scope(StockMovement::query(), 'stock_movements.farm_id', 'stock_movements.movement_date')
            ->where('stock_movements.type', StockMovementType::Out->value)
            ->whereIn('stock_movements.reason', array_map(fn (StockMovementReason $r) => $r->value, StockMovementReason::losses()));
    }

    /** Matériel volé. */
    public function equipmentTheftsQuery(): Builder
    {
        return $this->scope(EquipmentMovement::query(), 'equipment_movements.farm_id', 'equipment_movements.movement_date')
            ->where('equipment_movements.movement_type', EquipmentMovementType::Theft->value);
    }

    /** Vols et pertes de caisse. */
    public function cashTheftsQuery(): Builder
    {
        return $this->scope(Transaction::query(), 'transactions.farm_id', 'transactions.transaction_date')
            ->where('transactions.type', TransactionType::Debit->value)
            ->where('transactions.category', TransactionCategory::Theft->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function getKpis(): array
    {
        if ($this->kpisCache !== null) {
            return $this->kpisCache;
        }

        $birds = $this->recordsQuery()
            ->selectRaw('COALESCE(SUM(mortality), 0) AS mortality, COALESCE(SUM(culls), 0) AS culls, COALESCE(SUM(thefts), 0) AS thefts')
            ->first();

        $theft = StockMovementReason::Theft->value;
        $stock = $this->stockLossesQuery()
            ->selectRaw('COALESCE(SUM(total_price), 0) AS value')
            ->selectRaw("COALESCE(SUM(CASE WHEN reason = '{$theft}' THEN total_price ELSE 0 END), 0) AS theft_value")
            ->selectRaw("COUNT(*) FILTER (WHERE reason = '{$theft}') AS theft_count")
            ->selectRaw('COUNT(*) AS count')
            ->first();

        $equipment = $this->equipmentTheftsQuery()
            ->join('equipments', 'equipments.id', '=', 'equipment_movements.equipment_id')
            ->selectRaw('COUNT(*) AS count, COALESCE(SUM(COALESCE(equipments.current_value, equipments.purchase_price, 0)), 0) AS value')
            ->first();

        $cash = $this->cashTheftsQuery()
            ->selectRaw('COUNT(*) AS count, COALESCE(SUM(amount), 0) AS value')
            ->first();

        return $this->kpisCache = [
            'mortality' => (int) $birds->mortality,
            'culls' => (int) $birds->culls,
            'bird_thefts' => (int) $birds->thefts,
            'stock_loss_value' => (float) $stock->value,
            'stock_loss_count' => (int) $stock->count,
            'stock_theft_value' => (float) $stock->theft_value,
            'stock_theft_count' => (int) $stock->theft_count,
            'equipment_theft_count' => (int) $equipment->count,
            'equipment_theft_value' => (float) $equipment->value,
            'cash_theft_count' => (int) $cash->count,
            'cash_theft_value' => (float) $cash->value,
            'theft_value' => (float) $stock->theft_value + (float) $equipment->value + (float) $cash->value,
        ];
    }

    /**
     * Sujets perdus par bande sur la période.
     *
     * @return Collection<int, object>
     */
    public function getFlockLosses(): Collection
    {
        return $this->recordsQuery()
            ->join('flocks', 'flocks.id', '=', 'daily_records.flock_id')
            ->leftJoin('farms', 'farms.id', '=', 'daily_records.farm_id')
            ->selectRaw('flocks.id AS flock_id, flocks.code AS flock_code, flocks.name AS flock_name, farms.name AS farm_name')
            ->selectRaw('COALESCE(SUM(daily_records.mortality), 0) AS mortality, COALESCE(SUM(daily_records.culls), 0) AS culls, COALESCE(SUM(daily_records.thefts), 0) AS thefts')
            ->groupBy('flocks.id', 'flocks.code', 'flocks.name', 'farms.name')
            ->havingRaw('SUM(daily_records.mortality) + SUM(daily_records.culls) + SUM(daily_records.thefts) > 0')
            ->orderByRaw('SUM(daily_records.thefts) DESC, SUM(daily_records.mortality) DESC')
            ->get();
    }

    /**
     * @return Collection<int, StockMovement>
     */
    public function getStockLosses(): Collection
    {
        return $this->stockLossesQuery()
            ->with(['stock.farm', 'flock'])
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Collection<int, EquipmentMovement>
     */
    public function getEquipmentThefts(): Collection
    {
        return $this->equipmentTheftsQuery()
            ->with(['equipment', 'fromBuilding.farm'])
            ->orderByDesc('movement_date')
            ->get();
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function getCashThefts(): Collection
    {
        return $this->cashTheftsQuery()
            ->with(['account.farm'])
            ->orderByDesc('transaction_date')
            ->get();
    }

    public function buildXlsx(): string
    {
        $kpis = $this->getKpis();

        return $this->xlsxToString(function (callable $addRow) use ($kpis): void {
            $addRow(['Synthèse']);
            $addRow(['Mortalité (sujets)', $kpis['mortality']]);
            $addRow(['Réformes (sujets)', $kpis['culls']]);
            $addRow(['Sujets volés', $kpis['bird_thefts']]);
            $addRow(['Pertes de stock (XOF)', $kpis['stock_loss_value']]);
            $addRow(['dont vols (XOF)', $kpis['stock_theft_value']]);
            $addRow(['Matériel volé (XOF)', $kpis['equipment_theft_value']]);
            $addRow(['Vols de caisse (XOF)', $kpis['cash_theft_value']]);
            $addRow(['Total des vols (XOF)', $kpis['theft_value']]);
            $addRow([]);

            $addRow(['Sujets perdus par bande']);
            $addRow(['Ferme', 'Bande', 'Mortalité', 'Réformes', 'Vols']);
            foreach ($this->getFlockLosses() as $row) {
                $addRow([$row->farm_name ?? '', $row->flock_code.($row->flock_name ? ' – '.$row->flock_name : ''), (int) $row->mortality, (int) $row->culls, (int) $row->thefts]);
            }
            $addRow([]);

            $addRow(['Pertes et vols de stock']);
            $addRow(['Date', 'Ferme', 'Produit', 'Motif', 'Quantité', 'Unité', 'Valeur (XOF)']);
            foreach ($this->getStockLosses() as $movement) {
                $addRow([
                    $movement->movement_date?->format('d/m/Y'),
                    $movement->stock?->farm?->name ?? 'Organisation',
                    $movement->stock?->name,
                    $movement->reason?->getLabel(),
                    (float) $movement->quantity,
                    $movement->stock?->unit,
                    (float) $movement->total_price,
                ]);
            }
            $addRow([]);

            $addRow(['Matériel volé']);
            $addRow(['Date', 'Équipement', 'N° inventaire', 'Valeur (XOF)', 'Circonstances']);
            foreach ($this->getEquipmentThefts() as $movement) {
                $addRow([
                    $movement->movement_date?->format('d/m/Y'),
                    $movement->equipment?->name,
                    $movement->equipment?->inventory_number,
                    (float) ($movement->equipment?->current_value ?? $movement->equipment?->purchase_price ?? 0),
                    $movement->reason,
                ]);
            }
            $addRow([]);

            $addRow(['Vols et pertes de caisse']);
            $addRow(['Date', 'Compte', 'Montant (XOF)', 'Description']);
            foreach ($this->getCashThefts() as $transaction) {
                $addRow([
                    $transaction->transaction_date?->format('d/m/Y'),
                    $transaction->account?->name,
                    (float) $transaction->amount,
                    $transaction->description,
                ]);
            }
        });
    }
}
