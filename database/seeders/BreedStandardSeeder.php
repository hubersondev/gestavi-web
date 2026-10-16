<?php

namespace Database\Seeders;

use App\Models\BreedStandard;
use Illuminate\Database\Seeder;

/**
 * Courbes de référence des souches courantes en Côte d'Ivoire (chair : Cobb 500, Ross 308 ;
 * pondeuses : ISA Brown, Lohmann Brown). Valeurs indicatives issues des guides d'élevage ;
 * ajustables depuis le panel Admin. Idempotent.
 */
class BreedStandardSeeder extends Seeder
{
    /**
     * @var array<string, array{type: string, points: list<array<string, int|float>>}>
     */
    private const CURVES = [
        'Cobb 500' => ['type' => BreedStandard::TYPE_BROILER, 'points' => [
            ['age_days' => 7, 'expected_weight_g' => 170, 'expected_feed_intake_g' => 155, 'expected_mortality_cumul' => 0.7],
            ['age_days' => 14, 'expected_weight_g' => 460, 'expected_feed_intake_g' => 550, 'expected_mortality_cumul' => 1.2],
            ['age_days' => 21, 'expected_weight_g' => 910, 'expected_feed_intake_g' => 1250, 'expected_mortality_cumul' => 1.6],
            ['age_days' => 28, 'expected_weight_g' => 1450, 'expected_feed_intake_g' => 2250, 'expected_mortality_cumul' => 2.0],
            ['age_days' => 35, 'expected_weight_g' => 2050, 'expected_feed_intake_g' => 3500, 'expected_mortality_cumul' => 2.3],
            ['age_days' => 42, 'expected_weight_g' => 2700, 'expected_feed_intake_g' => 4950, 'expected_mortality_cumul' => 2.6],
            ['age_days' => 49, 'expected_weight_g' => 3300, 'expected_feed_intake_g' => 6550, 'expected_mortality_cumul' => 2.9],
        ]],
        'Ross 308' => ['type' => BreedStandard::TYPE_BROILER, 'points' => [
            ['age_days' => 7, 'expected_weight_g' => 180, 'expected_feed_intake_g' => 160, 'expected_mortality_cumul' => 0.6],
            ['age_days' => 14, 'expected_weight_g' => 480, 'expected_feed_intake_g' => 570, 'expected_mortality_cumul' => 1.1],
            ['age_days' => 21, 'expected_weight_g' => 950, 'expected_feed_intake_g' => 1300, 'expected_mortality_cumul' => 1.5],
            ['age_days' => 28, 'expected_weight_g' => 1520, 'expected_feed_intake_g' => 2350, 'expected_mortality_cumul' => 1.9],
            ['age_days' => 35, 'expected_weight_g' => 2150, 'expected_feed_intake_g' => 3650, 'expected_mortality_cumul' => 2.2],
            ['age_days' => 42, 'expected_weight_g' => 2850, 'expected_feed_intake_g' => 5150, 'expected_mortality_cumul' => 2.5],
            ['age_days' => 49, 'expected_weight_g' => 3500, 'expected_feed_intake_g' => 6800, 'expected_mortality_cumul' => 2.8],
        ]],
        'ISA Brown' => ['type' => BreedStandard::TYPE_LAYER, 'points' => [
            ['age_days' => 7, 'expected_weight_g' => 65, 'expected_feed_intake_g' => 120, 'expected_mortality_cumul' => 0.5],
            ['age_days' => 14, 'expected_weight_g' => 130, 'expected_feed_intake_g' => 350, 'expected_mortality_cumul' => 0.8],
            ['age_days' => 28, 'expected_weight_g' => 290, 'expected_feed_intake_g' => 900, 'expected_mortality_cumul' => 1.2],
            ['age_days' => 42, 'expected_weight_g' => 470, 'expected_feed_intake_g' => 1650, 'expected_mortality_cumul' => 1.5],
            ['age_days' => 56, 'expected_weight_g' => 670, 'expected_feed_intake_g' => 2550, 'expected_mortality_cumul' => 1.8],
            ['age_days' => 70, 'expected_weight_g' => 870, 'expected_feed_intake_g' => 3550, 'expected_mortality_cumul' => 2.0],
            ['age_days' => 84, 'expected_weight_g' => 1070, 'expected_feed_intake_g' => 4650, 'expected_mortality_cumul' => 2.2],
            ['age_days' => 98, 'expected_weight_g' => 1250, 'expected_feed_intake_g' => 5800, 'expected_mortality_cumul' => 2.4],
            ['age_days' => 112, 'expected_weight_g' => 1400, 'expected_feed_intake_g' => 7000, 'expected_mortality_cumul' => 2.5],
            ['age_days' => 126, 'expected_weight_g' => 1520, 'expected_feed_intake_g' => 8200, 'expected_mortality_cumul' => 2.6, 'expected_laying_rate' => 5],
            ['age_days' => 140, 'expected_weight_g' => 1620, 'expected_feed_intake_g' => 9500, 'expected_mortality_cumul' => 2.7, 'expected_laying_rate' => 50],
            ['age_days' => 154, 'expected_weight_g' => 1720, 'expected_feed_intake_g' => 10900, 'expected_mortality_cumul' => 2.8, 'expected_laying_rate' => 90],
            ['age_days' => 168, 'expected_weight_g' => 1800, 'expected_feed_intake_g' => 12300, 'expected_mortality_cumul' => 2.9, 'expected_laying_rate' => 95],
            ['age_days' => 252, 'expected_weight_g' => 1900, 'expected_feed_intake_g' => 21500, 'expected_mortality_cumul' => 3.5, 'expected_laying_rate' => 93],
            ['age_days' => 336, 'expected_weight_g' => 1950, 'expected_feed_intake_g' => 31000, 'expected_mortality_cumul' => 4.2, 'expected_laying_rate' => 88],
            ['age_days' => 420, 'expected_weight_g' => 2000, 'expected_feed_intake_g' => 40500, 'expected_mortality_cumul' => 5.0, 'expected_laying_rate' => 82],
            ['age_days' => 504, 'expected_weight_g' => 2050, 'expected_feed_intake_g' => 50000, 'expected_mortality_cumul' => 6.0, 'expected_laying_rate' => 75],
        ]],
        'Lohmann Brown' => ['type' => BreedStandard::TYPE_LAYER, 'points' => [
            ['age_days' => 7, 'expected_weight_g' => 68, 'expected_feed_intake_g' => 125, 'expected_mortality_cumul' => 0.5],
            ['age_days' => 14, 'expected_weight_g' => 135, 'expected_feed_intake_g' => 360, 'expected_mortality_cumul' => 0.8],
            ['age_days' => 28, 'expected_weight_g' => 300, 'expected_feed_intake_g' => 920, 'expected_mortality_cumul' => 1.2],
            ['age_days' => 42, 'expected_weight_g' => 485, 'expected_feed_intake_g' => 1700, 'expected_mortality_cumul' => 1.5],
            ['age_days' => 56, 'expected_weight_g' => 690, 'expected_feed_intake_g' => 2620, 'expected_mortality_cumul' => 1.8],
            ['age_days' => 70, 'expected_weight_g' => 900, 'expected_feed_intake_g' => 3650, 'expected_mortality_cumul' => 2.0],
            ['age_days' => 84, 'expected_weight_g' => 1100, 'expected_feed_intake_g' => 4780, 'expected_mortality_cumul' => 2.2],
            ['age_days' => 98, 'expected_weight_g' => 1280, 'expected_feed_intake_g' => 5950, 'expected_mortality_cumul' => 2.4],
            ['age_days' => 112, 'expected_weight_g' => 1430, 'expected_feed_intake_g' => 7180, 'expected_mortality_cumul' => 2.5],
            ['age_days' => 126, 'expected_weight_g' => 1550, 'expected_feed_intake_g' => 8400, 'expected_mortality_cumul' => 2.6, 'expected_laying_rate' => 8],
            ['age_days' => 140, 'expected_weight_g' => 1650, 'expected_feed_intake_g' => 9700, 'expected_mortality_cumul' => 2.7, 'expected_laying_rate' => 55],
            ['age_days' => 154, 'expected_weight_g' => 1750, 'expected_feed_intake_g' => 11100, 'expected_mortality_cumul' => 2.8, 'expected_laying_rate' => 92],
            ['age_days' => 168, 'expected_weight_g' => 1830, 'expected_feed_intake_g' => 12550, 'expected_mortality_cumul' => 2.9, 'expected_laying_rate' => 96],
            ['age_days' => 252, 'expected_weight_g' => 1920, 'expected_feed_intake_g' => 22000, 'expected_mortality_cumul' => 3.5, 'expected_laying_rate' => 94],
            ['age_days' => 336, 'expected_weight_g' => 1980, 'expected_feed_intake_g' => 31700, 'expected_mortality_cumul' => 4.2, 'expected_laying_rate' => 89],
            ['age_days' => 420, 'expected_weight_g' => 2030, 'expected_feed_intake_g' => 41400, 'expected_mortality_cumul' => 5.0, 'expected_laying_rate' => 83],
            ['age_days' => 504, 'expected_weight_g' => 2080, 'expected_feed_intake_g' => 51100, 'expected_mortality_cumul' => 6.0, 'expected_laying_rate' => 76],
        ]],
    ];

    public function run(): void
    {
        foreach (self::CURVES as $breed => $curve) {
            $previous = null;

            foreach ($curve['points'] as $point) {
                // Aliment par jour et gain quotidien dérivés de la courbe cumulée entre deux points
                $perDay = null;
                $dailyGain = null;

                if ($previous !== null) {
                    $days = $point['age_days'] - $previous['age_days'];
                    $perDay = $days > 0 ? round(($point['expected_feed_intake_g'] - $previous['expected_feed_intake_g']) / $days, 2) : null;
                    $dailyGain = $days > 0 ? round(($point['expected_weight_g'] - $previous['expected_weight_g']) / $days, 2) : null;
                } else {
                    $perDay = round($point['expected_feed_intake_g'] / max(1, $point['age_days']), 2);
                    $dailyGain = round($point['expected_weight_g'] / max(1, $point['age_days']), 2);
                }

                BreedStandard::query()->updateOrCreate(
                    ['breed' => $breed, 'bird_type' => $curve['type'], 'age_days' => $point['age_days']],
                    array_merge($point, [
                        'expected_feed_per_day_g' => $perDay,
                        'expected_daily_gain_g' => $dailyGain,
                        'expected_fcr' => $point['expected_weight_g'] > 0 ? round($point['expected_feed_intake_g'] / $point['expected_weight_g'], 2) : null,
                        'is_active' => true,
                    ]),
                );

                $previous = $point;
            }
        }
    }
}
