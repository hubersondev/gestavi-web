<?php

namespace Database\Factories;

use App\Enums\FlockStatus;
use App\Models\Building;
use App\Models\ChickenType;
use App\Models\Flock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Flock>
 */
class FlockFactory extends Factory
{
    protected $model = Flock::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantityStarted = fake()->numberBetween(500, 10000);
        $pricePerUnit = fake()->randomFloat(2, 200, 2000);

        return [
            'building_id' => Building::factory(),
            'chicken_type_id' => ChickenType::factory(),
            'code' => strtoupper(fake()->unique()->bothify('LOT-###')),
            'name' => 'Bande '.fake()->randomLetter().fake()->randomDigit(),
            'quantity_started' => $quantityStarted,
            'quantity_current' => fake()->numberBetween(
                (int) ($quantityStarted * 0.9),
                $quantityStarted
            ),
            'age_at_arrival' => fake()->randomElement([0, 1, 7, 14, 21]),
            'start_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'expected_end_date' => fake()->dateTimeBetween('+1 month', '+6 months'),
            'actual_end_date' => null,
            'status' => FlockStatus::Active,
            'purchase_price_per_unit' => $pricePerUnit,
            'total_purchase_price' => round($quantityStarted * $pricePerUnit, 2),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function planned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FlockStatus::Planned,
            'start_date' => fake()->dateTimeBetween('+1 week', '+1 month'),
            'quantity_current' => 0,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FlockStatus::Active,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FlockStatus::Completed,
            'actual_end_date' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FlockStatus::Cancelled,
            'actual_end_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'quantity_current' => 0,
        ]);
    }
}
