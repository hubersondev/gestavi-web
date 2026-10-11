<?php

namespace Database\Factories;

use App\Models\ChickenType;
use App\Models\City;
use App\Models\Farm;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Farm>
 */
class FarmFactory extends Factory
{
    protected $model = Farm::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'code' => strtoupper(fake()->unique()->bothify('FARM-###')),
            'name' => 'Ferme '.fake()->word(),
            'capacity' => fake()->numberBetween(500, 20000),
            'address' => fake()->address(),
            'city_id' => fn (): int => City::query()->value('id') ?? City::factory()->create()->id,
            'latitude' => fake()->latitude(5.0, 10.0),
            'longitude' => fake()->longitude(-8.0, -2.0),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'chicken_type_id' => ChickenType::factory(),
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
