<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\Veterinarian;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Veterinarian>
 */
class VeterinarianFactory extends Factory
{
    protected $model = Veterinarian::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'name' => 'Dr '.fake()->lastName(),
            'phone' => '+22507'.fake()->numerify('########'),
            'clinic' => fake()->company(),
            'is_active' => true,
        ];
    }
}
