<?php

namespace Database\Factories;

use App\Models\Equipment;
use App\Models\EquipmentMovement;
use Database\Factories\Concerns\InheritsOrganisationFromParent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EquipmentMovement>
 */
class EquipmentMovementFactory extends Factory
{
    use InheritsOrganisationFromParent;

    protected $model = EquipmentMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => $this->organisationIdFrom('equipment_id', Equipment::class),
            'farm_id' => $this->farmIdFrom('equipment_id', Equipment::class),
            'equipment_id' => fn (array $attributes): int => Equipment::factory()->create($this->scopeAttributes($attributes))->id,
            'movement_type' => fake()->randomElement(['transfer', 'entry', 'exit', 'loan', 'return']),
            'movement_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'reason' => fake()->optional()->sentence(),
        ];
    }

    public function transfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'movement_type' => 'transfer',
        ]);
    }

    public function entry(): static
    {
        return $this->state(fn (array $attributes) => [
            'movement_type' => 'entry',
        ]);
    }
}
