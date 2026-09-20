<?php

namespace App\Services;

use App\Enums\ExpenseStatus;
use App\Enums\FlockStatus;
use App\Enums\SaleStatus;
use App\Models\DailyRecord;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\FarmDailyMetric;
use App\Models\Flock;
use App\Models\Payment;
use App\Models\Sale;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Calcule et stocke les agrégats quotidiens par ferme à partir des tables de faits.
 * Idempotent : une nouvelle exécution sur la même journée remplace l'agrégat.
 */
class FarmMetricsAggregator
{
    /**
     * Agrège toutes les fermes actives sur une période (par défaut : hier et aujourd'hui).
     * Retourne le nombre d'agrégats écrits.
     */
    public function aggregate(?CarbonInterface $from = null, ?CarbonInterface $to = null, ?int $organisationId = null): int
    {
        $from = ($from ?? now()->subDay())->copy()->startOfDay();
        $to = ($to ?? now())->copy()->startOfDay();

        $farms = Farm::query()
            ->when($organisationId, fn ($q) => $q->where('organisation_id', $organisationId))
            ->get(['id', 'organisation_id']);

        $written = 0;

        foreach ($farms as $farm) {
            $production = $this->productionByDate($farm->id, $from, $to);
            $sales = $this->sumByDate(Sale::query()->where('farm_id', $farm->id)->where('status', '!=', SaleStatus::Cancelled->value), 'sale_date', 'total', $from, $to);
            $payments = $this->sumByDate(Payment::query()->where('farm_id', $farm->id), 'payment_date', 'amount', $from, $to);
            $expenses = $this->sumByDate(Expense::query()->where('farm_id', $farm->id)->where('status', '!=', ExpenseStatus::Cancelled->value), 'expense_date', 'amount', $from, $to);

            for ($day = $from->copy(); $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
                $key = $day->toDateString();
                $row = $production->get($key);

                FarmDailyMetric::query()->updateOrCreate(
                    ['farm_id' => $farm->id, 'metric_date' => $key],
                    [
                        'organisation_id' => $farm->organisation_id,
                        'active_flocks' => $this->activeFlocksOn($farm->id, $day),
                        'birds_present' => (int) ($row?->birds_present ?? $this->birdsPresentOn($farm->id, $day)),
                        'mortality' => (int) ($row?->mortality ?? 0),
                        'culls' => (int) ($row?->culls ?? 0),
                        'eggs_collected' => (int) ($row?->eggs_collected ?? 0),
                        'eggs_broken' => (int) ($row?->eggs_broken ?? 0),
                        'feed_kg' => (float) ($row?->feed_kg ?? 0),
                        'water_l' => (float) ($row?->water_l ?? 0),
                        'sales_total' => (float) ($sales[$key] ?? 0),
                        'payments_total' => (float) ($payments[$key] ?? 0),
                        'expenses_total' => (float) ($expenses[$key] ?? 0),
                        'computed_at' => now(),
                    ],
                );

                $written++;
            }
        }

        return $written;
    }

    /**
     * Production agrégée par jour depuis les relevés quotidiens de la ferme.
     */
    private function productionByDate(int $farmId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return DailyRecord::query()
            ->where('farm_id', $farmId)
            ->whereBetween('record_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('record_date, SUM(birds_present) AS birds_present, SUM(mortality) AS mortality, SUM(culls) AS culls, SUM(eggs_collected) AS eggs_collected, SUM(eggs_broken) AS eggs_broken, SUM(feed_consumed_kg) AS feed_kg, SUM(water_consumed_l) AS water_l')
            ->groupBy('record_date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->record_date)->toDateString());
    }

    /**
     * @return array<string, float>
     */
    private function sumByDate($query, string $dateColumn, string $amountColumn, CarbonInterface $from, CarbonInterface $to): array
    {
        return $query
            ->whereBetween($dateColumn, [$from->toDateString(), $to->toDateString()])
            ->selectRaw("{$dateColumn} AS d, SUM({$amountColumn}) AS total")
            ->groupBy('d')
            ->pluck('total', 'd')
            ->mapWithKeys(fn ($total, $date) => [Carbon::parse($date)->toDateString() => (float) $total])
            ->all();
    }

    private function activeFlocksOn(int $farmId, CarbonInterface $day): int
    {
        return Flock::query()
            ->where('farm_id', $farmId)
            ->whereIn('status', [FlockStatus::Active->value, FlockStatus::Completed->value])
            ->whereDate('start_date', '<=', $day->toDateString())
            ->where(fn ($q) => $q->whereNull('actual_end_date')->orWhereDate('actual_end_date', '>=', $day->toDateString()))
            ->count();
    }

    /**
     * Effectif présent d'une ferme un jour sans relevé : dernier effectif connu de chaque bande active.
     */
    private function birdsPresentOn(int $farmId, CarbonInterface $day): int
    {
        return (int) Flock::query()
            ->where('farm_id', $farmId)
            ->where('status', FlockStatus::Active->value)
            ->whereDate('start_date', '<=', $day->toDateString())
            ->sum('quantity_current');
    }
}
