<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Country>
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Codes de la plage « à usage privé » de l'ISO 3166 (AA, QM–QZ, XA–XZ, ZZ) : jamais un vrai pays,
            // donc jamais en collision avec le référentiel seedé (CI, FR…) ni ses codes à trois lettres
            'code' => $code = fake()->unique()->regexify('(Q[M-Z]|X[A-Z])'),
            'name' => 'Pays de test '.$code,
            'code_iso3' => $code.strtoupper(fake()->randomLetter()),
            'phone_code' => '+'.fake()->numberBetween(1, 999),
            'currency_id' => null,
            'default_language' => 'fr',
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
