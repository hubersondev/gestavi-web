<?php

namespace Database\Factories;

use App\Models\DailyRecord;
use App\Models\Flock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DailyRecord>
 */
class DailyRecordFactory extends Factory
{
    protected $model = DailyRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'flock_id' => Flock::factory()->active(),
            'record_date' => fake()->dateTimeBetween('-10 days', 'now')->format('Y-m-d'),
            'age_days' => fake()->numberBetween(1, 60),
            'mortality' => fake()->numberBetween(0, 5),
            'culls' => 0,
            'eggs_collected' => fake()->numberBetween(0, 500),
            'eggs_broken' => fake()->numberBetween(0, 10),
            'feed_consumed_kg' => fake()->randomFloat(2, 10, 200),
            'water_consumed_l' => fake()->randomFloat(2, 20, 400),
            'temperature_min' => fake()->randomFloat(1, 22, 28),
            'temperature_max' => fake()->randomFloat(1, 28, 36),
        ];
    }
}
