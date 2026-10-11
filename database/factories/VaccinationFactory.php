<?php

namespace Database\Factories;

use App\Enums\VaccinationStatus;
use App\Models\Flock;
use App\Models\Vaccination;
use Database\Factories\Concerns\InheritsOrganisationFromParent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vaccination>
 */
class VaccinationFactory extends Factory
{
    use InheritsOrganisationFromParent;

    protected $model = Vaccination::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => $this->organisationIdFrom('flock_id', Flock::class),
            'farm_id' => $this->farmIdFrom('flock_id', Flock::class),
            'flock_id' => fn (array $attributes): int => Flock::factory()->active()->create($this->scopeAttributes($attributes))->id,
            'treatment_name' => fake()->randomElement(['Newcastle HB1', 'Gumboro', 'Bronchite infectieuse', 'Vermifuge']),
            'treatment_type' => fake()->randomElement(['vaccin', 'vermifuge', 'vitamine']),
            'scheduled_date' => fake()->dateTimeBetween('-5 days', '+20 days')->format('Y-m-d'),
            'age_at_treatment' => fake()->numberBetween(1, 120),
            'administration_route' => fake()->randomElement(['eau', 'injection', 'spray', 'oculaire']),
            'withdrawal_days' => 0,
            'status' => VaccinationStatus::Pending,
        ];
    }
}
