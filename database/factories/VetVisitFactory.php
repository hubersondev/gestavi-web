<?php

namespace Database\Factories;

use App\Enums\VetVisitType;
use App\Models\Farm;
use App\Models\VetVisit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VetVisit>
 */
class VetVisitFactory extends Factory
{
    protected $model = VetVisit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'visit_date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'visit_type' => fake()->randomElement(VetVisitType::cases()),
            'reason' => fake()->sentence(),
            'consultation_fee' => fake()->numberBetween(10000, 50000),
            'medication_cost' => fake()->numberBetween(0, 30000),
        ];
    }
}
