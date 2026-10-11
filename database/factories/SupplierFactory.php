<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Country;
use App\Models\Organisation;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Fournisseur réseau par défaut (organisation sans ferme) ; passer farm_id pour une branche de ferme,
            // l'organisation est alors déduite de la ferme par le trait BelongsToOrganisation.
            'organisation_id' => fn (array $attributes) => isset($attributes['farm_id']) ? null : Organisation::factory()->create()->id,
            'farm_id' => null,
            'name' => fake()->company(),
            'code' => strtoupper(fake()->unique()->bothify('SUP-###')),
            'type' => fake()->randomElement(['aliment', 'medicament', 'poussin', 'materiel', 'equipement']),
            'contact_name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->optional()->companyEmail(),
            'address' => fake()->optional()->address(),
            // Référentiels partagés : réutiliser un pays/une ville existants (les codes pays sont uniques)
            'country_id' => fn (): int => Country::query()->value('id') ?? Country::factory()->create()->id,
            'city_id' => fn (array $attributes): int => City::query()->where('country_id', $attributes['country_id'])->value('id')
                ?? City::factory()->create(['country_id' => $attributes['country_id']])->id,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
