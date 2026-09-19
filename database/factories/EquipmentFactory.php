<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Equipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Equipment>
 */
class EquipmentFactory extends Factory
{
    protected $model = Equipment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'building_id' => Building::factory(),
            'inventory_number' => strtoupper(fake()->unique()->bothify('EQP-#####')),
            'name' => 'Équipement '.fake()->randomLetter().fake()->randomDigit(),
            'serial_number' => fake()->optional()->bothify('SN-####-????'),
            'category' => fake()->randomElement([
                'alimentation', 'abreuvement', 'ventilation', 'chauffage',
                'eclairage', 'pesage', 'transport', 'nettoyage', 'stockage', 'autre',
            ]),
            'brand' => fake()->optional()->company(),
            'model' => fake()->optional()->word(),
            'capacity' => fake()->optional()->numberBetween(1, 1000),
            'capacity_unit' => fake()->optional()->randomElement(['litres', 'kg', 'unités']),
            'purchase_date' => fake()->optional()->date(),
            'purchase_price' => fake()->optional()->randomFloat(2, 5000, 500000),
            'warranty_expiry_date' => fake()->optional()->dateTimeBetween('now', '+3 years'),
            'status' => fake()->randomElement(['in_service', 'in_stock', 'in_maintenance', 'out_of_service']),
            'condition' => fake()->randomElement(['new', 'good', 'fair', 'poor']),
            'current_value' => fake()->optional()->randomFloat(2, 1000, 300000),
            'lifespan_years' => fake()->optional()->numberBetween(1, 15),
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function inService(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_service',
        ]);
    }

    public function inStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_stock',
            'building_id' => null,
        ]);
    }

    public function disposed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'disposed',
            'building_id' => null,
        ]);
    }
}
