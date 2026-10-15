<?php

namespace App\Services;

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseStatus;
use App\Enums\PayrollStatus;
use App\Enums\SaleStatus;
use App\Enums\StockCategoryType;
use App\Enums\StockMovementType;
use App\Models\DailyRecord;
use App\Models\Expense;
use App\Models\Flock;
use App\Models\HealthIncident;
use App\Models\Payroll;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\Vaccination;
use App\Models\VetVisit;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Coût de revient et marge d'une bande.
 *
 * Coûts directs (imputés à la bande) :
 *  - poussins : prix d'achat total de la bande
 *  - aliment : sorties de stock de catégorie « aliment » liées à la bande (valorisées au CMUP du mouvement)
 *    + dépenses de catégorie « aliment » imputées à la bande
 *  - santé : sorties de stock « médicaments » liées à la bande + coût des vaccinations,
 *    des visites vétérinaires et des traitements d'incidents + dépenses « médicaments/vétérinaire »
 *  - autres dépenses directes : toute dépense imputée à la bande dans une autre catégorie
 *
 * Coûts indirects (ferme) répartis au prorata des sujets-jours :
 *  - main-d'œuvre : bulletins de paie validés/payés des employés de la ferme (brut + charges employeur)
 *    sur les mois de présence de la bande, hors dépenses « salaires » déjà imputées directement
 *  - charges de ferme : dépenses de la ferme non imputées à une bande (hors salaires) sur la période
 *
 * Clé de répartition = sujets-jours de la bande / sujets-jours de toutes les bandes de la ferme
 * actives sur la même période. Une bande seule sur sa ferme supporte 100 % des charges.
 *
 * Ensembliste : breakdownMany() lit chaque table de faits une fois pour toutes les bandes demandées
 * (sommes groupées par bande, par ferme et par date), puis assemble chaque bande en mémoire. Le banc
 * de charge comptait 72 requêtes par bande avec le calcul bande par bande.
 */
class FlockCostService
{
    /**
     * @return array<string, mixed>
     */
    public function breakdown(Flock $flock): array
    {
        return $this->breakdownMany($flock->newCollection([$flock]))[$flock->id];
    }

    /**
     * Coût de revient de plusieurs bandes, indexé par identifiant de bande.
     *
     * @param  EloquentCollection<int, Flock>  $flocks
     * @return array<int, array<string, mixed>>
     */
    public function breakdownMany(EloquentCollection $flocks): array
    {
        if ($flocks->isEmpty()) {
            return [];
        }

        $flocks->loadMissing('chickenType');
        $flockIds = $flocks->pluck('id')->all();
        $farmIds = $flocks->pluck('farm_id')->filter()->unique()->values()->all();

        $periods = [];

        foreach ($flocks as $flock) {
            $periods[$flock->id] = $this->period($flock);
        }

        $earliest = min(array_map(fn (array $p): string => $p[0]->toDateString(), $periods));
        $latest = max(array_map(fn (array $p): string => $p[1]->toDateString(), $periods));

        $stockOut = $this->stockOutByFlockAndType($flockIds);
        $expenses = $this->directExpensesByFlockAndCategory($flockIds);
        $vaccinations = $this->sumByFlock(Vaccination::query(), 'cost', $flockIds);
        $vetVisits = $this->sumByFlock(VetVisit::query(), 'total_cost', $flockIds);
        $incidents = $this->sumByFlock(HealthIncident::query(), 'treatment_cost', $flockIds);
        $revenues = $this->revenueByFlock($flockIds);
        $eggs = $this->sumByFlock(DailyRecord::query(), 'eggs_collected', $flockIds);
        $lastWeights = $this->lastWeightByFlock($flockIds);
        $payrollByFarmMonth = $this->payrollByFarmAndMonth($farmIds, $earliest, $latest);
        $overheadsByFarmDate = $this->indirectExpensesByFarmAndDate($farmIds, $earliest, $latest);
        $farmFlocks = $this->flocksByFarm($farmIds);

        $results = [];

        foreach ($flocks as $flock) {
            [$start, $end] = $periods[$flock->id];
            $byCategory = $expenses[$flock->id] ?? [];

            $chicks = (float) ($flock->total_purchase_price ?? 0);
            $feedFromStock = (float) ($stockOut[$flock->id][StockCategoryType::Feed->value] ?? 0);
            $feedFromExpenses = (float) ($byCategory[ExpenseCategory::Feed->value] ?? 0);
            $feed = $feedFromStock + $feedFromExpenses;

            $health = (float) ($stockOut[$flock->id][StockCategoryType::Medicine->value] ?? 0)
                + (float) ($vaccinations[$flock->id] ?? 0)
                + (float) ($vetVisits[$flock->id] ?? 0)
                + (float) ($incidents[$flock->id] ?? 0)
                + (float) ($byCategory[ExpenseCategory::Medicine->value] ?? 0)
                + (float) ($byCategory[ExpenseCategory::Veterinary->value] ?? 0);

            $directLabour = (float) ($byCategory[ExpenseCategory::Salaries->value] ?? 0);
            $otherDirect = 0.0;

            foreach ($byCategory as $category => $amount) {
                if (! in_array($category, [ExpenseCategory::Feed->value, ExpenseCategory::Medicine->value, ExpenseCategory::Veterinary->value, ExpenseCategory::Salaries->value], true)) {
                    $otherDirect += (float) $amount;
                }
            }

            $allocationShare = $this->allocationShareAmong($flock, $farmFlocks[$flock->farm_id] ?? collect(), $start, $end);
            $allocatedLabour = round($this->payrollCostFor($flock, $payrollByFarmMonth, $start, $end) * $allocationShare, 2);
            $allocatedOverheads = round($this->indirectExpensesFor($flock, $overheadsByFarmDate, $start, $end) * $allocationShare, 2);

            $labour = $directLabour + $allocatedLabour;
            $total = $chicks + $feed + $health + $labour + $otherDirect + $allocatedOverheads;

            $revenue = (float) ($revenues[$flock->id] ?? 0);
            $margin = $revenue - $total;

            $started = max(1, (int) $flock->quantity_started);
            $alive = (int) $flock->quantity_current + (int) $flock->quantity_sold;
            $eggCount = (int) ($eggs[$flock->id] ?? 0);
            $lastWeight = (float) ($lastWeights[$flock->id] ?? 0);
            $liveWeightKg = $alive > 0 && $lastWeight > 0 ? ($alive * $lastWeight) / 1000 : 0.0;

            $results[$flock->id] = [
                'periode' => ['du' => $start->toDateString(), 'au' => $end->toDateString(), 'jours' => (int) $start->diffInDays($end) + 1],
                'cle_repartition' => round($allocationShare * 100, 2),
                'couts' => [
                    'poussins' => round($chicks, 2),
                    'aliment' => round($feed, 2),
                    'aliment_stock' => round($feedFromStock, 2),
                    'aliment_depenses' => round($feedFromExpenses, 2),
                    'sante' => round($health, 2),
                    'main_oeuvre' => round($labour, 2),
                    'main_oeuvre_directe' => round($directLabour, 2),
                    'main_oeuvre_repartie' => $allocatedLabour,
                    'autres_directs' => round($otherDirect, 2),
                    'charges_ferme_reparties' => $allocatedOverheads,
                    'total' => round($total, 2),
                ],
                'produits' => [
                    'ventes' => round($revenue, 2),
                ],
                'marge' => [
                    'brute' => round($margin, 2),
                    'taux' => $revenue > 0 ? round(($margin / $revenue) * 100, 2) : null,
                ],
                'unitaires' => [
                    'cout_par_sujet_mis_en_place' => round($total / $started, 2),
                    'cout_par_sujet_vivant' => $alive > 0 ? round($total / $alive, 2) : null,
                    'cout_par_kg_vif' => $liveWeightKg > 0 ? round($total / $liveWeightKg, 2) : null,
                    'cout_par_oeuf' => $eggCount > 0 ? round($total / $eggCount, 2) : null,
                ],
            ];
        }

        return $results;
    }

    /**
     * Part des sujets-jours de la bande dans l'ensemble des bandes de la ferme
     * présentes sur la même période (1.0 si elle est seule).
     */
    public function allocationShare(Flock $flock, CarbonInterface $start, CarbonInterface $end): float
    {
        if (! $flock->farm_id) {
            return 1.0;
        }

        return $this->allocationShareAmong($flock, $this->flocksByFarm([$flock->farm_id])[$flock->farm_id] ?? collect(), $start, $end);
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function period(Flock $flock): array
    {
        $start = $flock->start_date?->copy()->startOfDay() ?? $flock->created_at?->copy()->startOfDay() ?? now()->startOfDay();
        $end = ($flock->actual_end_date ?? now())->copy()->endOfDay();

        return [$start, $end];
    }

    /**
     * @param  Collection<int, Flock>  $farmFlocks  toutes les bandes de la ferme, datées
     */
    private function allocationShareAmong(Flock $flock, Collection $farmFlocks, CarbonInterface $start, CarbonInterface $end): float
    {
        if (! $flock->farm_id) {
            return 1.0;
        }

        $own = $this->birdDays($flock, $start, $end);

        if ($own <= 0) {
            return 1.0;
        }

        $siblings = $farmFlocks->filter(fn (Flock $sibling): bool => $sibling->id !== $flock->id
            && $sibling->start_date !== null
            && $sibling->start_date->lessThanOrEqualTo($end)
            && ($sibling->actual_end_date === null || $sibling->actual_end_date->greaterThanOrEqualTo($start->copy()->startOfDay())));

        $total = $own + $siblings->sum(fn (Flock $sibling): float => $this->birdDays($sibling, $start, $end));

        return $total > 0 ? $own / $total : 1.0;
    }

    /**
     * Sujets-jours approximés : effectif moyen (initial + courant) / 2 × jours de présence dans la période.
     */
    private function birdDays(Flock $flock, CarbonInterface $start, CarbonInterface $end): float
    {
        $from = Carbon::parse($flock->start_date ?? $start)->max($start);
        $to = Carbon::parse($flock->actual_end_date ?? $end)->min($end);

        if ($to->lessThan($from)) {
            return 0.0;
        }

        $days = (int) $from->diffInDays($to) + 1;
        $averageBirds = ((int) $flock->quantity_started + (int) $flock->quantity_current + (int) $flock->quantity_sold) / 2;

        return $averageBirds * $days;
    }

    // ─── Lectures groupées ──────────────────────────────────────────────────

    /**
     * Valeur des sorties de stock par bande et par type de catégorie (aliment, médicaments…).
     *
     * @param  list<int>  $flockIds
     * @return array<int, array<string, float>>
     */
    private function stockOutByFlockAndType(array $flockIds): array
    {
        $values = [];

        StockMovement::query()
            ->join('stocks', 'stocks.id', '=', 'stock_movements.stock_id')
            ->join('stock_categories', 'stock_categories.id', '=', 'stocks.stock_category_id')
            ->whereIn('stock_movements.flock_id', $flockIds)
            ->where('stock_movements.type', StockMovementType::Out->value)
            ->selectRaw('stock_movements.flock_id, stock_categories.type AS category_type, SUM(stock_movements.total_price) AS total')
            ->groupBy('stock_movements.flock_id', 'stock_categories.type')
            ->toBase()
            ->get()
            ->each(function ($row) use (&$values): void {
                $values[(int) $row->flock_id][(string) $row->category_type] = (float) $row->total;
            });

        return $values;
    }

    /**
     * Dépenses (non annulées) imputées directement aux bandes, par bande et par catégorie.
     *
     * @param  list<int>  $flockIds
     * @return array<int, array<string, float>>
     */
    private function directExpensesByFlockAndCategory(array $flockIds): array
    {
        $values = [];

        Expense::query()
            ->whereIn('flock_id', $flockIds)
            ->where('status', '!=', ExpenseStatus::Cancelled->value)
            ->selectRaw('flock_id, category, SUM(amount) AS total')
            ->groupBy('flock_id', 'category')
            ->toBase()
            ->get()
            ->each(function ($row) use (&$values): void {
                $values[(int) $row->flock_id][(string) $row->category] = (float) $row->total;
            });

        return $values;
    }

    /**
     * @param  list<int>  $flockIds
     * @return array<int, float>
     */
    private function sumByFlock($query, string $column, array $flockIds): array
    {
        return $query
            ->whereIn('flock_id', $flockIds)
            ->selectRaw("flock_id, SUM({$column}) AS total")
            ->groupBy('flock_id')
            ->toBase()
            ->pluck('total', 'flock_id')
            ->map(fn ($total): float => (float) $total)
            ->all();
    }

    /**
     * Chiffre d'affaires des lignes de vente liées à chaque bande (factures non annulées).
     *
     * @param  list<int>  $flockIds
     * @return array<int, float>
     */
    private function revenueByFlock(array $flockIds): array
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereIn('sale_items.flock_id', $flockIds)
            ->where('sales.status', '!=', SaleStatus::Cancelled->value)
            ->selectRaw('sale_items.flock_id, SUM(sale_items.total) AS total')
            ->groupBy('sale_items.flock_id')
            ->toBase()
            ->pluck('total', 'flock_id')
            ->map(fn ($total): float => (float) $total)
            ->all();
    }

    /**
     * Dernier poids moyen relevé de chaque bande.
     *
     * @param  list<int>  $flockIds
     * @return array<int, float>
     */
    private function lastWeightByFlock(array $flockIds): array
    {
        return DailyRecord::query()
            ->whereIn('flock_id', $flockIds)
            ->whereNotNull('average_weight_g')
            ->selectRaw('DISTINCT ON (flock_id) flock_id, average_weight_g')
            ->orderBy('flock_id')
            ->orderByDesc('record_date')
            ->orderByDesc('id')
            ->toBase()
            ->pluck('average_weight_g', 'flock_id')
            ->map(fn ($weight): float => (float) $weight)
            ->all();
    }

    /**
     * Coût employeur (brut + charges) des bulletins validés ou payés, par ferme et par mois.
     *
     * @param  list<int>  $farmIds
     * @return array<int, array<string, float>> farm_id => 'AAAA-MM' => coût
     */
    private function payrollByFarmAndMonth(array $farmIds, string $earliest, string $latest): array
    {
        if ($farmIds === []) {
            return [];
        }

        $values = [];
        $from = Carbon::parse($earliest)->startOfMonth();
        $to = Carbon::parse($latest)->endOfMonth();

        Payroll::query()
            ->whereIn('farm_id', $farmIds)
            ->whereIn('status', [PayrollStatus::Validated->value, PayrollStatus::Paid->value])
            ->whereRaw('make_date(period_year, period_month, 1) BETWEEN ? AND ?', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('farm_id, period_year, period_month, SUM(gross_salary) + SUM(cnps_employer) AS total')
            ->groupBy('farm_id', 'period_year', 'period_month')
            ->toBase()
            ->get()
            ->each(function ($row) use (&$values): void {
                $values[(int) $row->farm_id][sprintf('%04d-%02d', $row->period_year, $row->period_month)] = (float) $row->total;
            });

        return $values;
    }

    /**
     * @param  array<int, array<string, float>>  $payrollByFarmMonth
     */
    private function payrollCostFor(Flock $flock, array $payrollByFarmMonth, CarbonInterface $start, CarbonInterface $end): float
    {
        if (! $flock->farm_id || ! isset($payrollByFarmMonth[$flock->farm_id])) {
            return 0.0;
        }

        $total = 0.0;
        $cursor = $start->copy()->startOfMonth();

        while ($cursor->lessThanOrEqualTo($end)) {
            $total += $payrollByFarmMonth[$flock->farm_id][$cursor->format('Y-m')] ?? 0.0;
            $cursor = $cursor->addMonth(); // CarbonImmutable : addMonth() retourne une nouvelle instance
        }

        return $total;
    }

    /**
     * Dépenses de ferme non imputées à une bande (hors salaires), par ferme et par jour.
     *
     * @param  list<int>  $farmIds
     * @return array<int, array<string, float>> farm_id => 'AAAA-MM-JJ' => montant
     */
    private function indirectExpensesByFarmAndDate(array $farmIds, string $earliest, string $latest): array
    {
        if ($farmIds === []) {
            return [];
        }

        $values = [];

        Expense::query()
            ->whereIn('farm_id', $farmIds)
            ->whereNull('flock_id')
            ->where('status', '!=', ExpenseStatus::Cancelled->value)
            ->where('category', '!=', ExpenseCategory::Salaries->value)
            ->whereBetween('expense_date', [$earliest, $latest])
            ->selectRaw('farm_id, expense_date, SUM(amount) AS total')
            ->groupBy('farm_id', 'expense_date')
            ->toBase()
            ->get()
            ->each(function ($row) use (&$values): void {
                $values[(int) $row->farm_id][Carbon::parse($row->expense_date)->toDateString()] = (float) $row->total;
            });

        return $values;
    }

    /**
     * @param  array<int, array<string, float>>  $overheadsByFarmDate
     */
    private function indirectExpensesFor(Flock $flock, array $overheadsByFarmDate, CarbonInterface $start, CarbonInterface $end): float
    {
        if (! $flock->farm_id || ! isset($overheadsByFarmDate[$flock->farm_id])) {
            return 0.0;
        }

        $from = $start->toDateString();
        $to = $end->toDateString();
        $total = 0.0;

        foreach ($overheadsByFarmDate[$flock->farm_id] as $date => $amount) {
            if ($date >= $from && $date <= $to) {
                $total += $amount;
            }
        }

        return $total;
    }

    /**
     * Bandes datées de chaque ferme, pour la clé de répartition.
     *
     * @param  list<int>  $farmIds
     * @return array<int, Collection<int, Flock>>
     */
    private function flocksByFarm(array $farmIds): array
    {
        if ($farmIds === []) {
            return [];
        }

        return Flock::query()
            ->whereIn('farm_id', $farmIds)
            ->whereNotNull('start_date')
            ->get(['id', 'farm_id', 'start_date', 'actual_end_date', 'quantity_started', 'quantity_current', 'quantity_sold'])
            ->groupBy('farm_id')
            ->all();
    }
}
