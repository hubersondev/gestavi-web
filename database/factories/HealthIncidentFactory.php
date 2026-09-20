<?php

namespace Database\Factories;

use App\Enums\IncidentCategory;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Models\Flock;
use App\Models\HealthIncident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HealthIncident>
 */
class HealthIncidentFactory extends Factory
{
    protected $model = HealthIncident::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'flock_id' => Flock::factory()->active(),
            'title' => 'Incident '.fake()->word(),
            'incident_date' => fake()->dateTimeBetween('-10 days', 'now')->format('Y-m-d'),
            'severity' => fake()->randomElement(IncidentSeverity::cases()),
            'category' => fake()->randomElement(IncidentCategory::cases()),
            'status' => IncidentStatus::cases()[0],
            'symptoms' => fake()->sentence(),
            'birds_affected' => fake()->numberBetween(1, 50),
            'birds_dead' => 0,
        ];
    }
}
