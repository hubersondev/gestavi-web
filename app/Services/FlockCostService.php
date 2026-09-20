<?php

namespace App\Services;

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseStatus;
use App\Enums\PayrollStatus;
use App\Enums\SaleStatus;
use App\Enums\StockCategoryType;
use App\Enums\StockMovementType;
use App\Models\Expense;
use App\Models\Flock;
use App\Models\Payroll;
use App\Models\SaleItem;
use App\Models\StockMovement;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

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
 */
class FlockCostService
{
    /**
     * @return array<string, mixed>
     */
    public function breakdown(Flock $flock): array
    {
        $flock->loadMissing('chickenType');

        $start = $flock->start_date?->copy()->startOfDay() ?? $flock->created_at?->copy()->startOfDay() ?? now()->startOfDay();
        $end = ($flock->actual_end_date ?? now())->copy()->endOfDay();

        $chicks = (float) ($flock->total_purchase_price ?? 0);

        $feedFromStock = $this->stockOutValue($flock, StockCategoryType::Feed);
        $feedFromExpenses = $this->directExpenses($flock, [ExpenseCategory::Feed]);
        $feed = $feedFromStock + $feedFromExpenses;

        $health = $this->stockOutValue($flock, StockCategoryType::Medicine)
            + (float) $flock->vaccinations()->sum('cost')
            + (float) $flock->vetVisits()->sum('total_cost')
            + (float) $flock->healthIncidents()->sum('treatment_cost')
            + $this->directExpenses($flock, [ExpenseCategory::Medicine, ExpenseCategory::Veterinary]);

        $directLabour = $this->directExpenses($flock, [ExpenseCategory::Salaries]);
        $otherDirect = $this->directExpenses($flock, exclude: [
            ExpenseCategory::Feed, ExpenseCategory::Medicine, ExpenseCategory::Veterinary, ExpenseCategory::Salaries,
        ]);

        $allocationShare = $this->allocationShare($flock, $start, $end);
        $allocatedLabour = round($this->farmPayrollCost($flock, $start, $end) * $allocationShare, 2);
        $allocatedOverheads = round($this->farmIndirectExpenses($flock, $start, $end) * $allocationShare, 2);

        $labour = $directLabour + $allocatedLabour;

        $total = $chicks + $feed + $health + $labour + $otherDirect + $allocatedOverheads;

        $revenue = $this->revenue($flock);
        $margin = $revenue - $total;

        $started = max(1, (int) $flock->quantity_started);
        $alive = (int) $flock->quantity_current + (int) $flock->quantity_sold;
        $eggs = (int) $flock->dailyRecords()->sum('eggs_collected');
        $lastWeight = (float) ($flock->dailyRecords()->whereNotNull('average_weight_g')->orderByDesc('record_date')->value('average_weight_g') ?? 0);
        $liveWeightKg = $alive > 0 && $lastWeight > 0 ? ($alive * $lastWeight) / 1000 : 0.0;

        return [
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
                'cout_par_oeuf' => $eggs > 0 ? round($total / $eggs, 2) : null,
            ],
        ];
    }

    /**
     * Valeur des sorties de stock d'une catégorie donnée imputées à la bande.
     */
    private function stockOutValue(Flock $flock, StockCategoryType $type): float
    {
        return (float) StockMovement::query()
            ->where('flock_id', $flock->id)
            ->where('type', StockMovementType::Out->value)
            ->whereHas('stock.category', fn ($q) => $q->where('type', $type->value))
            ->sum('total_price');
    }

    /**
     * Dépenses (non annulées) imputées directement à la bande.
     *
     * @param  list<ExpenseCategory>|null  $only
     * @param  list<ExpenseCategory>  $exclude
     */
    private function directExpenses(Flock $flock, ?array $only = null, array $exclude = []): float
    {
        return (float) Expense::query()
            ->where('flock_id', $flock->id)
            ->where('status', '!=', ExpenseStatus::Cancelled->value)
            ->when($only, fn ($q) => $q->whereIn('category', array_map(fn (ExpenseCategory $c) => $c->value, $only)))
            ->when($exclude, fn ($q) => $q->whereNotIn('category', array_map(fn (ExpenseCategory $c) => $c->value, $exclude)))
            ->sum('amount');
    }

    /**
     * Coût employeur des bulletins (brut + charges employeur) des employés de la ferme
     * pour les mois couverts par la période de la bande.
     */
    private function farmPayrollCost(Flock $flock, CarbonInterface $start, CarbonInterface $end): float
    {
        if (! $flock->farm_id) {
            return 0.0;
        }

        $months = [];
        $cursor = $start->copy()->startOfMonth();

        while ($cursor->lessThanOrEqualTo($end)) {
            $months[] = [$cursor->year, $cursor->month];
            $cursor = $cursor->addMonth(); // CarbonImmutable : addMonth() retourne une nouvelle instance
        }

        $query = Payroll::query()
            ->where('farm_id', $flock->farm_id)
            ->whereIn('status', [PayrollStatus::Validated->value, PayrollStatus::Paid->value])
            ->where(function ($q) use ($months): void {
                foreach ($months as [$year, $month]) {
                    $q->orWhere(fn ($m) => $m->where('period_year', $year)->where('period_month', $month));
                }
            });

        return (float) $query->sum('gross_salary') + (float) $query->clone()->sum('cnps_employer');
    }

    /**
     * Dépenses de la ferme non imputées à une bande (hors salaires, déjà couverts par la paie)
     * sur la période de la bande.
     */
    private function farmIndirectExpenses(Flock $flock, CarbonInterface $start, CarbonInterface $end): float
    {
        if (! $flock->farm_id) {
            return 0.0;
        }

        return (float) Expense::query()
            ->where('farm_id', $flock->farm_id)
            ->whereNull('flock_id')
            ->where('status', '!=', ExpenseStatus::Cancelled->value)
            ->where('category', '!=', ExpenseCategory::Salaries->value)
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');
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

        $own = $this->birdDays($flock, $start, $end);

        if ($own <= 0) {
            return 1.0;
        }

        $siblings = Flock::query()
            ->where('farm_id', $flock->farm_id)
            ->whereKeyNot($flock->id)
            ->whereNotNull('start_date')
            ->whereDate('start_date', '<=', $end->toDateString())
            ->where(fn ($q) => $q->whereNull('actual_end_date')->orWhereDate('actual_end_date', '>=', $start->toDateString()))
            ->get();

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

    /**
     * Chiffre d'affaires des lignes de vente liées à la bande (factures non annulées).
     */
    private function revenue(Flock $flock): float
    {
        return (float) SaleItem::query()
            ->where('flock_id', $flock->id)
            ->whereHas('sale', fn ($q) => $q->where('status', '!=', SaleStatus::Cancelled->value))
            ->sum('total');
    }
}
