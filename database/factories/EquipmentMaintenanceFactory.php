<?php

namespace Database\Factories;

use App\Models\Equipment;
use App\Models\EquipmentMaintenance;
use Database\Factories\Concerns\InheritsOrganisationFromParent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EquipmentMaintenance>
 */
class EquipmentMaintenanceFactory extends Factory
{
    use InheritsOrganisationFromParent;

    protected $model = EquipmentMaintenance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $laborCost = fake()->randomFloat(2, 0, 50000);
        $partsCost = fake()->randomFloat(2, 0, 100000);

        return [
            'organisation_id' => $this->organisationIdFrom('equipment_id', Equipment::class),
            'farm_id' => $this->farmIdFrom('equipment_id', Equipment::class),
            'equipment_id' => fn (array $attributes): int => Equipment::factory()->create($this->scopeAttributes($attributes))->id,
            'maintenance_type' => fake()->randomElement(['preventive', 'corrective', 'inspection', 'cleaning']),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'labor_cost' => $laborCost,
            'parts_cost' => $partsCost,
            'total_cost' => $laborCost + $partsCost,
            'scheduled_date' => fake()->dateTimeBetween('-1 month', '+1 month'),
            'status' => fake()->randomElement(['scheduled', 'in_progress', 'completed', 'cancelled']),
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scheduled',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'performed_date' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }
}
