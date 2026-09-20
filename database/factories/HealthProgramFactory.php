<?php

namespace Database\Factories;

use App\Models\ChickenType;
use App\Models\HealthProgram;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HealthProgram>
 */
class HealthProgramFactory extends Factory
{
    protected $model = HealthProgram::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'chicken_type_id' => ChickenType::factory(),
            'treatment_name' => fake()->randomElement(['Newcastle HB1', 'Gumboro', 'Anticoccidien']),
            'treatment_type' => 'vaccin',
            'scheduled_day' => fake()->numberBetween(1, 120),
            'administration_route' => 'eau',
            'withdrawal_days' => 0,
            'is_mandatory' => true,
        ];
    }
}
