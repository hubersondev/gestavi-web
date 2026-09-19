<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Farm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Building>
 */
class BuildingFactory extends Factory
{
    protected $model = Building::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'code' => strtoupper(fake()->unique()->bothify('BAT-###')),
            'name' => 'Bâtiment '.fake()->randomLetter().fake()->randomDigit(),
            'type' => fake()->randomElement(['poulailler', 'hangar', 'entrepôt', 'bureau']),
            'capacity' => fake()->numberBetween(500, 10000),
            'area_sqm' => fake()->randomFloat(2, 50, 2000),
            'description' => fake()->sentence(),
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
