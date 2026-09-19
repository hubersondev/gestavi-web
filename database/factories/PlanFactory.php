<?php

namespace Database\Factories;

use App\Enums\PlanFeature;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'monthly_price' => fake()->randomFloat(2, 0, 500),
            'yearly_price' => fake()->optional()->randomFloat(2, 0, 5000),
            'duration_months' => fake()->randomElement([1, 3, 6, 12]),
            'max_farms' => fake()->optional()->numberBetween(1, 50),
            'max_users' => fake()->optional()->numberBetween(1, 100),
            'trial_days' => fake()->randomElement([0, 7, 14, 30]),
            'features' => collect(PlanFeature::cases())
                ->random(fake()->numberBetween(5, 20))
                ->map(fn (PlanFeature $f) => $f->value)
                ->values()
                ->all(),
            'sort_order' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
