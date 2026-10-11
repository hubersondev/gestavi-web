<?php

namespace Database\Factories;

use App\Models\Flock;
use App\Models\Slaughter;
use Database\Factories\Concerns\InheritsOrganisationFromParent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Slaughter>
 */
class SlaughterFactory extends Factory
{
    use InheritsOrganisationFromParent;

    protected $model = Slaughter::class;

    public function definition(): array
    {
        $liveWeight = fake()->randomFloat(2, 50, 400);

        return [
            'organisation_id' => $this->organisationIdFrom('flock_id', Flock::class),
            'farm_id' => $this->farmIdFrom('flock_id', Flock::class),
            'flock_id' => fn (array $attributes): int => Flock::factory()->active()->create($this->scopeAttributes($attributes))->id,
            'slaughter_date' => now()->toDateString(),
            'birds_count' => fake()->numberBetween(10, 100),
            'live_weight_kg' => $liveWeight,
            'carcass_weight_kg' => round($liveWeight * 0.72, 2),
        ];
    }
}
