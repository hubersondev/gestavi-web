<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Equipment;
use App\Models\Farm;
use App\Models\Organisation;
use Database\Factories\Concerns\InheritsOrganisationFromParent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Equipment>
 */
class EquipmentFactory extends Factory
{
    use InheritsOrganisationFromParent;

    protected $model = Equipment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => function (array $attributes): int {
                if ($building = $this->parentKey($attributes, 'building_id')) {
                    return (int) Building::query()->whereKey($building)->value('organisation_id');
                }

                if ($farm = $this->parentKey($attributes, 'farm_id')) {
                    return (int) Farm::query()->whereKey($farm)->value('organisation_id');
                }

                return Organisation::factory()->create()->id;
            },
            'farm_id' => fn (array $attributes): int => ($building = $this->parentKey($attributes, 'building_id'))
                ? (int) Building::query()->whereKey($building)->value('farm_id')
                : Farm::factory()->create(['organisation_id' => $attributes['organisation_id']])->id,
            'building_id' => fn (array $attributes): int => Building::factory()->create(['farm_id' => $attributes['farm_id']])->id,
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
