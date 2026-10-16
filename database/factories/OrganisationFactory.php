<?php

namespace Database\Factories;

use App\Enums\OrganisationStatus;
use App\Models\City;
use App\Models\Country;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organisation>
 */
class OrganisationFactory extends Factory
{
    protected $model = Organisation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(4),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            // Référentiels partagés : réutiliser le pays et une ville existants avant d'en créer
            'country_id' => fn (): int => Country::query()->value('id') ?? Country::factory()->create()->id,
            'city_id' => fn (array $attributes): int => City::query()->where('country_id', $attributes['country_id'])->value('id')
                ?? City::factory()->create(['country_id' => $attributes['country_id']])->id,
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    public function readOnly(string $reason = 'Impayé en cours de régularisation'): static
    {
        return $this->state(fn (): array => ['status' => OrganisationStatus::ReadOnly, 'status_reason' => $reason]);
    }

    public function suspended(string $reason = 'Compte suspendu par la plateforme'): static
    {
        return $this->state(fn (): array => ['status' => OrganisationStatus::Suspended, 'is_active' => false, 'status_reason' => $reason]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
