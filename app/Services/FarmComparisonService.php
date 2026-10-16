<?php

namespace App\Services;

use App\Enums\ExpenseStatus;
use App\Enums\ProductionKind;
use App\Enums\SaleStatus;
use App\Models\DailyRecord;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\OrganisationUser;
use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Comparaison multi-fermes : pour 2 à 5 fermes et une période, indicateurs techniques
 * (mortalité, ponte, poids, aliment) et économiques (ventes, dépenses) calculés en SQL depuis
 * les relevés, les bandes, les ventes et les dépenses, puis scores normalisés 0–100 (100 = la
 * meilleure ferme sur l'axe) pour le radar. Filtre optionnel sur le type de production.
 */
class FarmComparisonService
{
    public const MIN_FARMS = 2;

    public const MAX_FARMS = 5;

    /** Axes du radar : clé → [libellé, indicateur source, « plus c'est haut mieux c'est »]. */
    public const AXES = [
        'survival' => ['Survie', 'survival_rate', true],
        'laying' => ['Ponte', 'laying_rate', true],
        'growth' => ['Croissance', 'avg_weight_g', true],
        'feed' => ['Sobriété aliment', 'feed_per_bird_day_g', false],
        'sales' => ['Ventes', 'sales', true],
    ];

    /** Indicateurs proposés aux graphiques en barres : clé → [libellé, unité, décimales]. */
    public const METRICS = [
        'mortality_rate' => ['Taux de mortalité', '%', 2],
        'laying_rate' => ['Taux de ponte', '%', 1],
        'avg_weight_g' => ['Poids moyen', 'g', 0],
        'feed_per_bird_day_g' => ['Aliment par sujet et par jour', 'g', 1],
        'eggs_per_day' => ['Œufs par jour', 'œufs', 0],
        'sales' => ['Ventes', 'XOF', 0],
        'expenses' => ['Dépenses', 'XOF', 0],
        'result' => ['Ventes − dépenses', 'XOF', 0],
    ];

    /**
     * Fermes qu'un membre peut comparer : toutes celles de l'organisation pour un rôle à accès
     * global, sinon celles qui lui sont affectées. Indépendant de la ferme sélectionnée dans le
     * sélecteur global, puisque comparer suppose d'en voir plusieurs.
     *
     * @return Collection<int, Farm>
     */
    public function comparableFarms(OrganisationUser $membership): Collection
    {
        $query = $membership->canAccessAllFarms()
            ? Farm::query()->where('organisation_id', $membership->organisation_id)
            : $membership->farms();

        return $query->where('farms.is_active', true)->orderBy('farms.name')->get(['farms.id', 'farms.name', 'farms.organisation_id']);
    }

    /**
     * Une ligne par ferme demandée (dans l'ordre demandé), même sans donnée.
     *
     * @param  list<int>  $farmIds
     * @return Collection<int, array<string, mixed>>
     */
    public function compare(int $organisationId, array $farmIds, string $dateFrom, string $dateTo, ?ProductionKind $kind = null): Collection
    {
        $farmIds = array_values(array_unique(array_map(intval(...), $farmIds)));

        if ($farmIds === []) {
            return collect();
        }

        $from = CarbonImmutable::parse($dateFrom)->startOfDay();
        $to = CarbonImmutable::parse($dateTo)->startOfDay();
        $periodDays = max(1, (int) $from->diffInDays($to) + 1);

        $farms = Farm::query()
            ->where('organisation_id', $organisationId)
            ->whereIn('id', $farmIds)
            ->get(['id', 'name'])
            ->keyBy('id');

        $records = $this->withKind(DailyRecord::query(), $kind, 'daily_records.flock_id')
            ->where('daily_records.organisation_id', $organisationId)
            ->whereIn('daily_records.farm_id', $farmIds)
            ->whereBetween('daily_records.record_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('daily_records.farm_id')
            ->selectRaw('COUNT(DISTINCT daily_records.flock_id) AS flocks')
            ->selectRaw('COALESCE(SUM(daily_records.mortality), 0) AS mortality')
            ->selectRaw('COALESCE(SUM(daily_records.culls), 0) AS culls')
            ->selectRaw('COALESCE(SUM(daily_records.thefts), 0) AS thefts')
            ->selectRaw('COALESCE(SUM(daily_records.eggs_collected), 0) AS eggs')
            ->selectRaw('COALESCE(SUM(daily_records.feed_consumed_kg), 0) AS feed_kg')
            ->selectRaw('COALESCE(SUM(daily_records.birds_present), 0) AS bird_days')
            ->selectRaw('AVG(NULLIF(daily_records.average_weight_g, 0)) AS avg_weight')
            ->groupBy('daily_records.farm_id')
            ->get()
            ->keyBy('farm_id');

        $active = $this->withKind(Flock::query()->active(), $kind, 'flocks.id')
            ->where('flocks.organisation_id', $organisationId)
            ->whereIn('flocks.farm_id', $farmIds)
            ->selectRaw('flocks.farm_id, COUNT(*) AS flocks, COALESCE(SUM(flocks.quantity_current), 0) AS birds')
            ->groupBy('flocks.farm_id')
            ->get()
            ->keyBy('farm_id');

        $sales = Sale::query()
            ->where('organisation_id', $organisationId)
            ->whereIn('farm_id', $farmIds)
            ->where('status', '!=', SaleStatus::Cancelled->value)
            ->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('farm_id, COALESCE(SUM(total), 0) AS total')
            ->groupBy('farm_id')
            ->pluck('total', 'farm_id');

        $expenses = Expense::query()
            ->where('organisation_id', $organisationId)
            ->whereIn('farm_id', $farmIds)
            ->where('status', '!=', ExpenseStatus::Cancelled->value)
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('farm_id, COALESCE(SUM(amount), 0) AS total')
            ->groupBy('farm_id')
            ->pluck('total', 'farm_id');

        return collect($farmIds)
            ->filter(fn (int $id): bool => $farms->has($id))
            ->map(function (int $id) use ($farms, $records, $active, $sales, $expenses, $periodDays): array {
                $r = $records->get($id);
                $a = $active->get($id);
                $birdDays = (int) ($r?->bird_days ?? 0);
                $mortality = (int) ($r?->mortality ?? 0);
                $eggs = (int) ($r?->eggs ?? 0);
                $feedKg = (float) ($r?->feed_kg ?? 0);
                $salesTotal = (float) ($sales[$id] ?? 0);
                $expensesTotal = (float) ($expenses[$id] ?? 0);
                $birds = (int) ($a?->birds ?? 0);
                $mortalityRate = $birdDays > 0 ? round($mortality / ($birdDays / $periodDays) * 100, 2) : null;

                return [
                    'farm_id' => $id,
                    'farm_name' => $farms[$id]->name,
                    'active_flocks' => (int) ($a?->flocks ?? 0),
                    'birds' => $birds,
                    'flocks_recorded' => (int) ($r?->flocks ?? 0),
                    'bird_days' => $birdDays,
                    'mortality' => $mortality,
                    'culls' => (int) ($r?->culls ?? 0),
                    'thefts' => (int) ($r?->thefts ?? 0),
                    'mortality_rate' => $mortalityRate,
                    'survival_rate' => $mortalityRate === null ? null : round(100 - $mortalityRate, 2),
                    'eggs' => $eggs,
                    'eggs_per_day' => round($eggs / $periodDays, 1),
                    'laying_rate' => $birdDays > 0 ? round($eggs / $birdDays * 100, 1) : null,
                    'avg_weight_g' => $r?->avg_weight !== null ? round((float) $r->avg_weight) : null,
                    'feed_kg' => round($feedKg, 1),
                    'feed_per_bird_day_g' => $birdDays > 0 ? round($feedKg * 1000 / $birdDays, 1) : null,
                    'sales' => round($salesTotal),
                    'expenses' => round($expensesTotal),
                    'result' => round($salesTotal - $expensesTotal),
                    'sales_per_bird' => $birds > 0 ? round($salesTotal / $birds) : null,
                ];
            })
            ->values();
    }

    /**
     * Scores 0–100 par ferme et par axe : 100 pour la meilleure ferme de l'axe, proportionnel
     * ensuite (pour « sobriété aliment », la plus faible consommation vaut 100). Les axes sans
     * objet pour le type de production sont omis (ponte en chair, croissance en ponte).
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{axes: array<string, string>, scores: array<int, array<string, float>>}
     */
    public function scores(Collection $rows, ?ProductionKind $kind = null): array
    {
        $axes = collect(self::AXES)
            ->reject(fn (array $axis, string $key): bool => ($kind === ProductionKind::Broiler && $key === 'laying')
                || ($kind === ProductionKind::Layer && $key === 'growth'));

        $scores = [];

        foreach ($axes as $key => [$label, $metric, $higherIsBetter]) {
            $values = $rows->pluck($metric, 'farm_id')->map(fn ($v) => $v === null ? null : (float) $v);
            $present = $values->filter(fn ($v) => $v !== null && $v > 0);
            $best = $higherIsBetter ? $present->max() : $present->min();

            foreach ($values as $farmId => $value) {
                $scores[(int) $farmId][$key] = match (true) {
                    $value === null, $value <= 0, ! $best => 0.0,
                    $higherIsBetter => round(min(100, $value / $best * 100), 1),
                    default => round(min(100, $best / $value * 100), 1),
                };
            }
        }

        return [
            'axes' => $axes->map(fn (array $axis): string => $axis[0])->all(),
            'scores' => $scores,
        ];
    }

    /**
     * Restreint les relevés ou bandes au type de production demandé (via le type de volaille).
     */
    private function withKind(Builder $query, ?ProductionKind $kind, string $flockIdColumn): Builder
    {
        if ($kind === null) {
            return $query;
        }

        return $query
            ->when($flockIdColumn !== 'flocks.id', fn (Builder $q) => $q->join('flocks', 'flocks.id', '=', $flockIdColumn))
            ->join('chicken_types', 'chicken_types.id', '=', 'flocks.chicken_type_id')
            ->where('chicken_types.production_kind', $kind->value);
    }
}
