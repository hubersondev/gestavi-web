<?php

namespace App\Services\Forecast;

use App\Enums\FlockStatus;
use App\Enums\PredictionType;
use App\Exceptions\ForecastException;
use App\Models\BreedStandard;
use App\Models\DailyRecord;
use App\Models\Flock;
use App\Models\FlockPrediction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Moteur de prévision par bande, statistique et explicable : pour chaque grandeur suivie
 * (mortalité cumulée, taux de ponte, poids moyen, aliment par sujet), la valeur à J+1…J+7 combine
 * une régression linéaire sur 14 jours, la tendance récente (moyenne mobile pondérée sur 7 jours,
 * ou projection du gain quotidien pour le poids) et la courbe de référence de la souche, ajustée
 * de l'écart déjà constaté. La confiance reflète la qualité de l'ajustement et l'écart au standard.
 */
class ForecastService
{
    public const MODEL_VERSION = 'v1-composite';

    public const MIN_RECORDS = 7;

    public const REGRESSION_WINDOW_DAYS = 14;

    public const WMA_WINDOW = 7;

    public const HORIZON_DAYS = 7;

    /**
     * Poids des trois composantes par grandeur : [régression, tendance, standard].
     *
     * @var array<string, array{0: float, 1: float, 2: float}>
     */
    private const WEIGHTS = [
        'mortality' => [0.40, 0.30, 0.30],
        'laying_rate' => [0.35, 0.35, 0.30],
        'weight' => [0.40, 0.30, 0.30],
        'feed_per_bird' => [0.40, 0.30, 0.30],
    ];

    /**
     * Génère les prévisions manquantes d'une bande active sur l'horizon.
     *
     * @return array{created: int, anomalies: list<array{type: PredictionType, flock: Flock, predicted: float, standard: float, target_age: int, message: string}>}
     */
    public function forecastFlock(Flock $flock, int $horizonDays = self::HORIZON_DAYS): array
    {
        if ($flock->status !== FlockStatus::Active) {
            throw ForecastException::flockNotActive($flock->code);
        }

        $records = $flock->dailyRecords()->orderBy('record_date')->get();

        if ($records->count() < self::MIN_RECORDS) {
            throw ForecastException::insufficientData($flock->code, self::MIN_RECORDS);
        }

        $today = Carbon::today();
        $created = 0;
        $anomalies = [];

        foreach ($this->typesFor($flock) as $type) {
            $series = $this->series($flock, $type, $records);

            if ($series['recent']->isEmpty()) {
                continue;
            }

            foreach (range(1, $horizonDays) as $day) {
                $target = $today->copy()->addDays($day);

                if (FlockPrediction::query()->where('flock_id', $flock->id)->forType($type)->whereDate('target_date', $target)->exists()) {
                    continue;
                }

                $estimate = $this->estimate($flock, $type, $series, $target);

                FlockPrediction::query()->create([
                    'organisation_id' => $flock->organisation_id,
                    'farm_id' => $flock->farm_id,
                    'flock_id' => $flock->id,
                    'type' => $type,
                    'prediction_date' => $today,
                    'target_date' => $target,
                    'target_age_days' => $estimate['target_age'],
                    'predicted_value' => $estimate['value'],
                    'confidence' => $estimate['confidence'],
                    'standard_value' => $estimate['standard'],
                    'factors' => $estimate['factors'],
                    'model_version' => self::MODEL_VERSION,
                ]);
                $created++;

                // Une seule alerte par grandeur, sur la prévision la plus lointaine
                if ($day === $horizonDays && ($anomaly = $this->anomalyFor($flock, $type, $estimate)) !== null) {
                    $anomalies[] = $anomaly;
                }
            }
        }

        return ['created' => $created, 'anomalies' => $anomalies];
    }

    /**
     * Renseigne la valeur réelle des prévisions échues d'une bande (relevé du jour cible, à défaut
     * le plus proche à ± 1 jour). Retourne le nombre de prévisions évaluées.
     */
    public function recordActuals(Flock $flock): int
    {
        $pending = FlockPrediction::query()
            ->where('flock_id', $flock->id)
            ->pending()
            ->whereDate('target_date', '<=', Carbon::today())
            ->get();

        if ($pending->isEmpty()) {
            return 0;
        }

        $records = $flock->dailyRecords()->orderBy('record_date')->get();
        $updated = 0;

        foreach ($pending as $prediction) {
            $target = $prediction->target_date;
            $record = $records->first(fn (DailyRecord $r): bool => $r->record_date->isSameDay($target))
                ?? $records
                    ->filter(fn (DailyRecord $r): bool => abs($r->record_date->diffInDays($target, false)) <= 1)
                    ->sortBy(fn (DailyRecord $r): int => abs($r->record_date->diffInDays($target, false)))
                    ->first();

            if (! $record) {
                continue;
            }

            $actual = $this->metricValue($flock, $prediction->type, $record, $records);

            if ($actual !== null) {
                $prediction->recordActual($actual);
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Toutes les bandes actives : valeurs réelles des prévisions échues, puis nouvelles prévisions.
     *
     * @return array{flocks: int, created: int, evaluated: int, anomalies: array<int, list<array<string, mixed>>>}
     */
    public function processAll(): array
    {
        $flocks = 0;
        $created = 0;
        $evaluated = 0;
        $anomalies = [];

        Flock::query()->active()->with(['chickenType', 'farm'])->chunkById(100, function ($chunk) use (&$flocks, &$created, &$evaluated, &$anomalies): void {
            foreach ($chunk as $flock) {
                $evaluated += $this->recordActuals($flock);

                try {
                    $result = $this->forecastFlock($flock);
                } catch (ForecastException) {
                    continue;
                }

                $flocks++;
                $created += $result['created'];

                foreach ($result['anomalies'] as $anomaly) {
                    $anomalies[(int) $flock->organisation_id][] = $anomaly;
                }
            }
        });

        return ['flocks' => $flocks, 'created' => $created, 'evaluated' => $evaluated, 'anomalies' => $anomalies];
    }

    /**
     * Grandeurs pertinentes pour une bande selon son type de production.
     *
     * @return list<PredictionType>
     */
    public function typesFor(Flock $flock): array
    {
        $kind = $flock->productionKind();
        $types = [PredictionType::Mortality, PredictionType::FeedPerBird];

        if ($kind->tracksEggs()) {
            $types[] = PredictionType::LayingRate;
        }

        if ($kind->tracksWeight()) {
            $types[] = PredictionType::Weight;
        }

        return $types;
    }

    /**
     * Valeur d'une grandeur lue dans un relevé (null si le relevé ne la renseigne pas).
     *
     * @param  Collection<int, DailyRecord>  $all  Tous les relevés de la bande (mortalité cumulée)
     */
    public function metricValue(Flock $flock, PredictionType $type, DailyRecord $record, Collection $all): ?float
    {
        return match ($type) {
            PredictionType::Mortality => (int) $flock->quantity_started > 0
                ? round($all->filter(fn (DailyRecord $r): bool => $r->record_date->lte($record->record_date))->sum('mortality') / (int) $flock->quantity_started * 100, 2)
                : null,
            PredictionType::LayingRate => (int) $record->eggs_collected > 0 ? $record->getLayingRate() : ($record->record_date->lt(Carbon::today()) ? 0.0 : null),
            PredictionType::Weight => $record->average_weight_g !== null && (float) $record->average_weight_g > 0 ? (float) $record->average_weight_g : null,
            PredictionType::FeedPerBird => (float) $record->feed_consumed_kg > 0 ? $record->getFeedPerBird() : null,
        };
    }

    /**
     * Série (âge, valeur) de la grandeur sur tous les relevés, et sa fenêtre récente.
     *
     * @param  Collection<int, DailyRecord>  $records
     * @return array{points: Collection<int, array{age: int, value: float, date: Carbon}>, recent: Collection<int, array{age: int, value: float, date: Carbon}>}
     */
    private function series(Flock $flock, PredictionType $type, Collection $records): array
    {
        $points = $records
            ->map(function (DailyRecord $record) use ($flock, $type, $records): ?array {
                $value = $this->metricValue($flock, $type, $record, $records);

                return $value === null ? null : [
                    'age' => $this->ageAt($flock, $record),
                    'value' => $value,
                    'date' => $record->record_date,
                ];
            })
            ->filter()
            ->values();

        $since = Carbon::today()->subDays(self::REGRESSION_WINDOW_DAYS);

        return [
            'points' => $points,
            'recent' => $points->filter(fn (array $p): bool => $p['date']->gte($since))->values(),
        ];
    }

    /**
     * @param  array{points: Collection, recent: Collection}  $series
     * @return array{value: float, confidence: float, standard: ?float, target_age: int, factors: list<array<string, mixed>>}
     */
    private function estimate(Flock $flock, PredictionType $type, array $series, Carbon $target): array
    {
        $recent = $series['recent'];
        $daysAhead = (int) Carbon::today()->diffInDays($target);
        $currentAge = (int) ($flock->getCurrentAgeInDays() ?? 0);
        $targetAge = $currentAge + $daysAhead;

        // 1. Régression linéaire valeur = f(âge) sur la fenêtre récente
        $regression = $this->linearRegression($recent->pluck('age')->all(), $recent->pluck('value')->all());
        $regressionValue = $regression['intercept'] + $regression['slope'] * $targetAge;

        // 2. Tendance : moyenne mobile pondérée prolongée (mortalité), projection du gain (poids), WMA (autres)
        $values = $recent->pluck('value')->all();
        $trendValue = $this->weightedMovingAverage($values, self::WMA_WINDOW);

        if ($type === PredictionType::Mortality && count($values) >= 2) {
            $trendValue += (end($values) - $values[count($values) - 2]) * $daysAhead;
        } elseif ($type === PredictionType::Weight) {
            $trendValue = $this->projectWeight($series['points'], $daysAhead);
        }

        // 3. Courbe de la souche, ajustée de l'écart réel/standard constaté aujourd'hui
        $kind = $flock->productionKind();
        $standardTarget = BreedStandard::expectedValue($flock->breed, $kind, $type->standardColumn(), $targetAge);
        $standardNow = BreedStandard::expectedValue($flock->breed, $kind, $type->standardColumn(), $currentAge);
        $standardValue = null;

        if ($standardTarget !== null) {
            $deviation = 0.0;

            if ($standardNow !== null && $standardNow > 0 && $recent->isNotEmpty()) {
                $deviation = max(-0.5, min(0.5, ((float) $recent->last()['value'] - $standardNow) / $standardNow));
            }

            $standardValue = $standardTarget * (1 + $deviation);
        }

        [$wRegression, $wTrend, $wStandard] = self::WEIGHTS[$type->value];

        if ($standardValue === null) {
            // Sans courbe de référence, les deux composantes observées se partagent le poids
            $total = $wRegression + $wTrend;
            $wRegression = $wRegression / $total;
            $wTrend = $wTrend / $total;
            $wStandard = 0.0;
        }

        $value = $wRegression * $regressionValue + $wTrend * $trendValue + $wStandard * ($standardValue ?? 0);
        $value = $this->clamp($type, $value);

        $confidence = $this->confidence($regression['r_squared'], $series['points']->count(), $value, $standardTarget);

        $factors = [
            ['key' => 'regression', 'label' => 'Tendance sur 14 jours', 'weight' => round($wRegression, 2), 'value' => round($this->clamp($type, $regressionValue), 2)],
            ['key' => 'trend', 'label' => $type === PredictionType::Weight ? 'Projection du gain quotidien' : 'Moyenne récente (7 jours)', 'weight' => round($wTrend, 2), 'value' => round($this->clamp($type, $trendValue), 2)],
        ];

        if ($standardValue !== null) {
            $factors[] = ['key' => 'standard', 'label' => 'Courbe de la souche '.$flock->breed, 'weight' => round($wStandard, 2), 'value' => round($standardValue, 2)];
        } else {
            $factors[] = ['key' => 'standard', 'label' => 'Courbe de la souche', 'weight' => 0, 'value' => null, 'note' => 'souche inconnue ou non renseignée'];
        }

        $factors[] = ['key' => 'age', 'label' => 'Âge de la bande au jour cible', 'weight' => 0, 'value' => $targetAge];

        return [
            'value' => round($value, 2),
            'confidence' => $confidence,
            'standard' => $standardTarget !== null ? round($standardTarget, 2) : null,
            'target_age' => $targetAge,
            'factors' => $factors,
        ];
    }

    /**
     * Écart préoccupant par rapport à la courbe de la souche : mortalité > 130 % du standard,
     * ponte sous le standard de plus de 15 points, poids sous 85 % du standard.
     *
     * @param  array{value: float, standard: ?float, target_age: int}  $estimate
     * @return array{type: PredictionType, flock: Flock, predicted: float, standard: float, target_age: int, message: string}|null
     */
    private function anomalyFor(Flock $flock, PredictionType $type, array $estimate): ?array
    {
        $standard = $estimate['standard'];

        if ($standard === null || $standard <= 0) {
            return null;
        }

        $predicted = $estimate['value'];

        $message = match ($type) {
            PredictionType::Mortality => $predicted > $standard * 1.3
                ? sprintf('Mortalité cumulée prévue %s %% contre %s %% attendus au jour %d', self::fmt($predicted), self::fmt($standard), $estimate['target_age'])
                : null,
            PredictionType::LayingRate => $predicted < $standard - 15
                ? sprintf('Taux de ponte prévu %s %% contre %s %% attendus au jour %d', self::fmt($predicted), self::fmt($standard), $estimate['target_age'])
                : null,
            PredictionType::Weight => $predicted < $standard * 0.85
                ? sprintf('Poids moyen prévu %s g contre %s g attendus au jour %d', self::fmt($predicted), self::fmt($standard), $estimate['target_age'])
                : null,
            PredictionType::FeedPerBird => null,
        };

        return $message === null ? null : [
            'type' => $type,
            'flock' => $flock,
            'predicted' => $predicted,
            'standard' => $standard,
            'target_age' => $estimate['target_age'],
            'message' => $message,
        ];
    }

    /**
     * Âge de la bande à la date du relevé, recalculé depuis la mise en place : la colonne
     * age_days d'un relevé peut avoir été figée à la saisie (correction tardive, import).
     */
    private function ageAt(Flock $flock, DailyRecord $record): int
    {
        if ($flock->start_date) {
            return (int) $flock->start_date->startOfDay()->diffInDays($record->record_date->startOfDay()) + (int) $flock->age_at_arrival;
        }

        return (int) ($record->age_days ?? 0);
    }

    /**
     * Poids projeté : dernier poids connu + gain moyen quotidien (entre premier et dernier point) × jours.
     *
     * @param  Collection<int, array{age: int, value: float, date: Carbon}>  $points
     */
    private function projectWeight(Collection $points, int $daysAhead): float
    {
        if ($points->isEmpty()) {
            return 0.0;
        }

        $last = $points->last();

        if ($points->count() < 2) {
            return (float) $last['value'];
        }

        $first = $points->first();
        $days = max(1, (int) $first['date']->diffInDays($last['date']));
        $gain = ((float) $last['value'] - (float) $first['value']) / $days;

        return (float) $last['value'] + $gain * $daysAhead;
    }

    private function clamp(PredictionType $type, float $value): float
    {
        return match ($type) {
            PredictionType::Mortality, PredictionType::LayingRate => max(0.0, min(100.0, $value)),
            default => max(0.0, $value),
        };
    }

    /**
     * Confiance 20–95 : qualité de la régression (R²), bonus au-delà de 14 relevés, malus d'un
     * point par pour cent d'écart au standard (plafonné à 20).
     */
    private function confidence(float $rSquared, int $dataPoints, float $predicted, ?float $standard): float
    {
        $confidence = min($rSquared * 100, 90);

        if ($dataPoints > 14) {
            $confidence += 5;
        }

        if ($standard !== null && $standard > 0) {
            $confidence -= min(abs(($predicted - $standard) / $standard) * 100, 20);
        }

        return (float) max(20, min(95, round($confidence, 2)));
    }

    /**
     * Moindres carrés : pente, ordonnée à l'origine et coefficient de détermination.
     *
     * @param  list<int|float>  $x
     * @param  list<int|float>  $y
     * @return array{slope: float, intercept: float, r_squared: float}
     */
    public function linearRegression(array $x, array $y): array
    {
        $n = count($x);

        if ($n === 0) {
            return ['slope' => 0.0, 'intercept' => 0.0, 'r_squared' => 0.0];
        }

        if ($n === 1) {
            return ['slope' => 0.0, 'intercept' => (float) $y[0], 'r_squared' => 0.0];
        }

        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0.0;
        $sumX2 = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumX2 += $x[$i] * $x[$i];
        }

        $denominator = $n * $sumX2 - $sumX * $sumX;

        if ($denominator == 0.0) {
            return ['slope' => 0.0, 'intercept' => $sumY / $n, 'r_squared' => 0.0];
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / $denominator;
        $intercept = ($sumY - $slope * $sumX) / $n;
        $meanY = $sumY / $n;
        $ssTot = 0.0;
        $ssRes = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $ssTot += ($y[$i] - $meanY) ** 2;
            $ssRes += ($y[$i] - ($intercept + $slope * $x[$i])) ** 2;
        }

        return [
            'slope' => $slope,
            'intercept' => $intercept,
            'r_squared' => $ssTot > 0 ? max(0.0, 1 - $ssRes / $ssTot) : 0.0,
        ];
    }

    /**
     * Moyenne mobile pondérée : les dernières valeurs pèsent davantage (poids 1, 2, …, n).
     *
     * @param  list<float>  $values
     */
    public function weightedMovingAverage(array $values, int $window): float
    {
        $slice = array_slice($values, -$window);

        if ($slice === []) {
            return 0.0;
        }

        $weighted = 0.0;
        $weights = 0;

        foreach (array_values($slice) as $i => $value) {
            $weighted += $value * ($i + 1);
            $weights += $i + 1;
        }

        return $weights > 0 ? $weighted / $weights : 0.0;
    }

    private static function fmt(float $value): string
    {
        return number_format($value, 1, ',', ' ');
    }
}
