<?php

namespace App\Services\Weather;

use App\Exceptions\WeatherException;
use App\Models\DailyRecord;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\WeatherForecast;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Météo des fermes : prévisions à 5 jours d'OpenWeatherMap agrégées par journée, géocodage de la
 * ville quand la ferme n'a pas de coordonnées, alertes comparées aux seuils de la phase de
 * production des bandes (canicule, froid, humidité, vent, pluie), et lecture de l'isolation des
 * bâtiments d'après les relevés intérieurs.
 */
class WeatherService
{
    /** Jours de prévision comparés aux seuils des phases. */
    public const ALERT_HORIZON_DAYS = 3;

    /** Vent fort : 13,9 m/s = 50 km/h. */
    public const WIND_ALERT_MS = 13.9;

    /** Fortes pluies : 50 mm sur la journée. */
    public const RAIN_ALERT_MM = 50.0;

    public static function enabled(): bool
    {
        return filled(config('services.openweathermap.key'));
    }

    // ─── RÉCUPÉRATION ─────────────────────────────────────────────────────────

    /**
     * Prévisions à 5 jours d'une ferme, agrégées par journée et enregistrées. Retourne le nombre
     * de journées enregistrées. Sans coordonnées ni ville géolocalisable, lève une exception.
     */
    public function fetchForecast(Farm $farm): int
    {
        if (! self::enabled()) {
            throw WeatherException::apiKeyMissing();
        }

        if (! $this->ensureCoordinates($farm)) {
            throw WeatherException::noCoordinates($farm->name);
        }

        $response = $this->request('/data/2.5/forecast', [
            'lat' => (float) $farm->latitude,
            'lon' => (float) $farm->longitude,
            'units' => 'metric',
            'lang' => 'fr',
        ]);

        $entries = $response['list'] ?? [];

        if ($entries === []) {
            return 0;
        }

        $saved = 0;

        foreach ($this->aggregateByDay($entries) as $date => $day) {
            WeatherForecast::query()->updateOrCreate(
                ['farm_id' => $farm->id, 'forecast_date' => $date, 'source' => WeatherForecast::SOURCE_OPENWEATHERMAP],
                $day + ['organisation_id' => $farm->organisation_id, 'fetched_at' => now()],
            );
            $saved++;
        }

        return $saved;
    }

    /**
     * Coordonnées de la ferme : celles saisies, sinon géocodage de sa ville (une fois, puis
     * mémorisées sur la ferme). Faux si rien ne permet de la situer.
     */
    public function ensureCoordinates(Farm $farm): bool
    {
        if ($farm->latitude !== null && $farm->longitude !== null) {
            return true;
        }

        $city = $farm->city;

        if (! $city) {
            return false;
        }

        $query = $city->name.($city->country?->code ? ','.$city->country->code : '');
        $results = $this->request('/geo/1.0/direct', ['q' => $query, 'limit' => 1]);
        $match = $results[0] ?? null;

        if (! is_array($match) || ! isset($match['lat'], $match['lon'])) {
            return false;
        }

        $farm->forceFill(['latitude' => $match['lat'], 'longitude' => $match['lon']])->save();

        return true;
    }

    // ─── LECTURE ──────────────────────────────────────────────────────────────

    public function current(Farm $farm): ?WeatherForecast
    {
        return WeatherForecast::query()->forFarm($farm->id)->whereDate('forecast_date', now()->toDateString())->first();
    }

    /**
     * @return Collection<int, WeatherForecast>
     */
    public function upcoming(Farm $farm, int $days = 5): Collection
    {
        return WeatherForecast::query()->forFarm($farm->id)->upcoming()->orderBy('forecast_date')->limit($days)->get();
    }

    public function lastFetchedAt(Farm $farm): ?Carbon
    {
        $value = WeatherForecast::query()->forFarm($farm->id)->max('fetched_at');

        return $value ? Carbon::parse($value) : null;
    }

    // ─── ALERTES ──────────────────────────────────────────────────────────────

    /**
     * Écarts entre les prévisions des prochains jours et les seuils de la phase de production
     * de chaque bande active de la ferme.
     *
     * @return list<array{flock: Flock, farm: Farm, date: Carbon, kind: string, critical: bool, message: string}>
     */
    public function anomaliesFor(Farm $farm): array
    {
        $forecasts = $this->upcoming($farm, self::ALERT_HORIZON_DAYS);

        if ($forecasts->isEmpty()) {
            return [];
        }

        $flocks = Flock::query()->active()->where('farm_id', $farm->id)->with(['chickenType', 'building.farm'])->get();
        $anomalies = [];

        foreach ($flocks as $flock) {
            $phase = $flock->getCurrentPhase();

            foreach ($forecasts as $forecast) {
                foreach ($this->evaluate($forecast, $phase?->max_temperature, $phase?->min_temperature, $phase?->humidity_max, $phase?->name) as $anomaly) {
                    $anomalies[] = $anomaly + ['flock' => $flock, 'farm' => $farm, 'date' => $forecast->forecast_date];
                }
            }
        }

        return $anomalies;
    }

    /**
     * Règles d'alerte pour une journée de prévision ; les seuils de phase absents désactivent
     * les règles correspondantes (vent et pluie restent universels).
     *
     * @return list<array{kind: string, critical: bool, message: string}>
     */
    public function evaluate(WeatherForecast $forecast, ?float $maxTemp, ?float $minTemp, ?float $maxHumidity, ?string $phaseName): array
    {
        $date = $forecast->forecast_date->format('d/m');
        $phase = $phaseName ? " (phase {$phaseName})" : '';
        $found = [];

        if ($forecast->temp_max !== null && $maxTemp !== null) {
            $gap = (float) $forecast->temp_max - $maxTemp;

            if ($gap >= 5) {
                $found[] = [
                    'kind' => 'heat',
                    'critical' => $gap >= 10,
                    'message' => sprintf('Canicule le %s : %s °C prévus, seuil %s °C%s', $date, self::n($forecast->temp_max), self::n($maxTemp), $phase),
                ];
            }
        }

        if ($forecast->temp_min !== null && $minTemp !== null) {
            $gap = $minTemp - (float) $forecast->temp_min;

            if ($gap >= 5) {
                $found[] = [
                    'kind' => 'cold',
                    'critical' => $gap >= 10,
                    'message' => sprintf('Froid le %s : %s °C prévus, seuil %s °C%s', $date, self::n($forecast->temp_min), self::n($minTemp), $phase),
                ];
            }
        }

        if ($forecast->humidity !== null && $maxHumidity !== null && (float) $forecast->humidity - $maxHumidity >= 20) {
            $found[] = [
                'kind' => 'humidity',
                'critical' => false,
                'message' => sprintf('Humidité élevée le %s : %s %% prévus, seuil %s %%%s', $date, self::n($forecast->humidity), self::n($maxHumidity), $phase),
            ];
        }

        if ($forecast->wind_speed !== null && (float) $forecast->wind_speed > self::WIND_ALERT_MS) {
            $found[] = [
                'kind' => 'wind',
                'critical' => false,
                'message' => sprintf('Vent fort le %s : %s km/h prévus, risque pour les bâtiments', $date, self::n($forecast->windKmh())),
            ];
        }

        if ($forecast->rain_mm !== null && (float) $forecast->rain_mm > self::RAIN_ALERT_MM) {
            $found[] = [
                'kind' => 'rain',
                'critical' => false,
                'message' => sprintf('Fortes pluies le %s : %s mm prévus, risque d\'inondation', $date, self::n($forecast->rain_mm)),
            ];
        }

        return $found;
    }

    // ─── ISOLATION DES BÂTIMENTS ──────────────────────────────────────────────

    /**
     * Compare, bande par bande, la température relevée hier dans le bâtiment à la température
     * extérieure du jour : un faible écart signe une isolation médiocre.
     *
     * @return list<array{flock: Flock, building: ?string, inside: float, outside: float, delta: float, score: string}>
     */
    public function buildingInsulation(Farm $farm): array
    {
        $forecast = $this->current($farm);

        if (! $forecast || $forecast->temp_avg === null) {
            return [];
        }

        $yesterday = now()->subDay()->toDateString();
        $results = [];

        foreach (Flock::query()->active()->where('farm_id', $farm->id)->with('building')->get() as $flock) {
            $record = DailyRecord::query()
                ->where('flock_id', $flock->id)
                ->whereDate('record_date', $yesterday)
                ->whereNotNull('temperature_min')
                ->whereNotNull('temperature_max')
                ->first();

            if (! $record) {
                continue;
            }

            $inside = ((float) $record->temperature_min + (float) $record->temperature_max) / 2;
            $outside = (float) $forecast->temp_avg;
            $delta = abs($inside - $outside);

            $results[] = [
                'flock' => $flock,
                'building' => $flock->building?->name,
                'inside' => round($inside, 1),
                'outside' => round($outside, 1),
                'delta' => round($inside - $outside, 1),
                'score' => match (true) {
                    $delta >= 6 => 'bonne',
                    $delta >= 3 => 'moyenne',
                    default => 'faible',
                },
            ];
        }

        return $results;
    }

    // ─── TRAITEMENT GLOBAL ────────────────────────────────────────────────────

    /**
     * Toutes les fermes actives : prévisions puis écarts aux seuils, regroupés par organisation.
     *
     * @return array{farms: int, forecasts: int, errors: list<string>, anomalies: array<int, list<array<string, mixed>>>}
     */
    public function processAll(): array
    {
        $farms = 0;
        $forecasts = 0;
        $errors = [];
        $anomalies = [];

        Farm::query()->where('is_active', true)->with(['city.country'])->chunkById(50, function ($chunk) use (&$farms, &$forecasts, &$errors, &$anomalies): void {
            foreach ($chunk as $farm) {
                try {
                    $forecasts += $this->fetchForecast($farm);
                    $farms++;
                } catch (WeatherException $e) {
                    $errors[] = $farm->name.' : '.$e->getMessage();
                    Log::warning('Météo : '.$farm->name, ['farm_id' => $farm->id, 'error' => $e->getMessage()]);

                    continue;
                }

                foreach ($this->anomaliesFor($farm) as $anomaly) {
                    $anomalies[(int) $farm->organisation_id][] = $anomaly;
                }
            }
        });

        return ['farms' => $farms, 'forecasts' => $forecasts, 'errors' => $errors, 'anomalies' => $anomalies];
    }

    // ─── PRIVÉ ────────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $query
     * @return array<int|string, mixed>
     */
    private function request(string $path, array $query): array
    {
        $base = rtrim((string) config('services.openweathermap.base_url', 'https://api.openweathermap.org'), '/');

        try {
            $response = Http::timeout(10)->retry(2, 1000)->get($base.$path, $query + ['appid' => config('services.openweathermap.key')]);
        } catch (\Throwable $e) {
            throw WeatherException::apiError($e->getMessage());
        }

        if ($response->failed()) {
            throw WeatherException::apiError($response->body(), $response->status());
        }

        return (array) $response->json();
    }

    /**
     * Agrège les tranches de 3 heures en journées : minimum des minima, maximum des maxima,
     * moyennes, cumul de pluie, description et icône les plus fréquentes.
     *
     * @param  list<array<string, mixed>>  $entries
     * @return array<string, array<string, mixed>>
     */
    public function aggregateByDay(array $entries): array
    {
        $byDay = [];

        foreach ($entries as $entry) {
            $date = substr((string) ($entry['dt_txt'] ?? ''), 0, 10);

            if ($date === '') {
                continue;
            }

            $byDay[$date][] = $entry;
        }

        $result = [];

        foreach ($byDay as $date => $dayEntries) {
            $pick = fn (string $group, string $key): array => array_values(array_filter(array_map(fn ($e) => $e[$group][$key] ?? null, $dayEntries), fn ($v) => $v !== null));
            $avg = fn (array $values): ?float => $values === [] ? null : round(array_sum($values) / count($values), 2);

            $temps = $pick('main', 'temp');
            $mins = $pick('main', 'temp_min');
            $maxs = $pick('main', 'temp_max');
            $descriptions = array_values(array_filter(array_map(fn ($e) => $e['weather'][0]['description'] ?? null, $dayEntries)));
            $icons = array_values(array_filter(array_map(fn ($e) => $e['weather'][0]['icon'] ?? null, $dayEntries)));

            $result[$date] = [
                'temp_min' => $mins === [] ? null : min($mins),
                'temp_max' => $maxs === [] ? null : max($maxs),
                'temp_avg' => $avg($temps),
                'humidity' => $avg($pick('main', 'humidity')),
                'pressure_hpa' => $avg($pick('main', 'pressure')),
                'wind_speed' => $avg($pick('wind', 'speed')),
                'wind_gust' => ($gusts = $pick('wind', 'gust')) === [] ? null : max($gusts),
                'rain_mm' => round(array_sum($pick('rain', '3h')), 2),
                'clouds_percent' => $avg($pick('clouds', 'all')),
                'description' => self::mostFrequent($descriptions),
                'icon' => self::mostFrequent($icons),
                'raw_data' => array_map(fn ($e) => ['dt_txt' => $e['dt_txt'] ?? null, 'main' => $e['main'] ?? null, 'wind' => $e['wind'] ?? null, 'rain' => $e['rain'] ?? null], $dayEntries),
            ];
        }

        ksort($result);

        return $result;
    }

    /**
     * @param  list<string>  $values
     */
    private static function mostFrequent(array $values): ?string
    {
        if ($values === []) {
            return null;
        }

        $counts = array_count_values($values);
        arsort($counts);

        return (string) array_key_first($counts);
    }

    private static function n(mixed $value): string
    {
        return number_format((float) $value, 0, ',', ' ');
    }
}
