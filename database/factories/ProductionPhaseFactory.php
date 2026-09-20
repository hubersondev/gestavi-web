<?php

namespace Database\Factories;

use App\Models\ChickenType;
use App\Models\Organisation;
use App\Models\ProductionPhase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductionPhase>
 */
class ProductionPhaseFactory extends Factory
{
    protected $model = ProductionPhase::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->numberBetween(1, 100);

        return [
            'organisation_id' => Organisation::factory(),
            'chicken_type_id' => ChickenType::factory(),
            'name' => 'Phase '.fake()->word(),
            'code' => strtoupper(fake()->unique()->bothify('PH_####')),
            'start_day' => $start,
            'end_day' => $start + fake()->numberBetween(5, 40),
            'min_temperature' => 20,
            'max_temperature' => 30,
            'order' => 1,
            'is_active' => true,
        ];
    }
}
