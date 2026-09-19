<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Country;
use App\Models\Farm;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'name' => fake()->company(),
            'code' => strtoupper(fake()->unique()->bothify('SUP-###')),
            'type' => fake()->randomElement(['aliment', 'medicament', 'poussin', 'materiel', 'equipement']),
            'contact_name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->optional()->companyEmail(),
            'address' => fake()->optional()->address(),
            'country_id' => Country::factory(),
            'city_id' => City::factory(),
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
