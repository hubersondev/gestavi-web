<?php

namespace Database\Factories;

use App\Models\Equipment;
use App\Models\EquipmentMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EquipmentMovement>
 */
class EquipmentMovementFactory extends Factory
{
    protected $model = EquipmentMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'equipment_id' => Equipment::factory(),
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
