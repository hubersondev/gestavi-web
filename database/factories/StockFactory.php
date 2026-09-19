<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\Stock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stock>
 */
class StockFactory extends Factory
{
    protected $model = Stock::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'name' => fake()->randomElement(['Aliment démarrage', 'Aliment croissance', 'Vaccin Newcastle', 'Vitamine C', 'Sac kraft 25kg']),
            'unit' => fake()->randomElement(['kg', 'sac', 'dose', 'L', 'unité']),
            'quantity' => fake()->numberBetween(0, 5000),
            'min_quantity' => fake()->numberBetween(10, 100),
            'unit_price' => fake()->numberBetween(100, 5000),
            'is_active' => true,
        ];
    }

    public function lowStock(): static
    {
        return $this->state(fn () => ['quantity' => 5, 'min_quantity' => 50]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
