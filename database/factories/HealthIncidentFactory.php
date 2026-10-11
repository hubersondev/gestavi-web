<?php

namespace Database\Factories;

use App\Enums\IncidentCategory;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Models\Flock;
use App\Models\HealthIncident;
use Database\Factories\Concerns\InheritsOrganisationFromParent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HealthIncident>
 */
class HealthIncidentFactory extends Factory
{
    use InheritsOrganisationFromParent;

    protected $model = HealthIncident::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => $this->organisationIdFrom('flock_id', Flock::class),
            'farm_id' => $this->farmIdFrom('flock_id', Flock::class),
            'flock_id' => fn (array $attributes): int => Flock::factory()->active()->create($this->scopeAttributes($attributes))->id,
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
