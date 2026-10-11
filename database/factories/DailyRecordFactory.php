<?php

namespace Database\Factories;

use App\Models\DailyRecord;
use App\Models\Flock;
use Database\Factories\Concerns\InheritsOrganisationFromParent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DailyRecord>
 */
class DailyRecordFactory extends Factory
{
    use InheritsOrganisationFromParent;

    protected $model = DailyRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => $this->organisationIdFrom('flock_id', Flock::class),
            'farm_id' => $this->farmIdFrom('flock_id', Flock::class),
            'flock_id' => fn (array $attributes): int => Flock::factory()->active()->create($this->scopeAttributes($attributes))->id,
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
