<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\OrganisationRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrganisationRole>
 */
class OrganisationRoleFactory extends Factory
{
    protected $model = OrganisationRole::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->jobTitle();

        return [
            'organisation_id' => Organisation::factory(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . fake()->unique()->randomNumber(4),
            'weight' => fake()->numberBetween(1, 4),
            'is_system' => false,
            'description' => fake()->sentence(),
        ];
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
        ]);
    }

    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Propriétaire',
            'slug' => 'owner',
            'weight' => 4,
            'is_system' => true,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Administrateur',
            'slug' => 'admin',
            'weight' => 3,
            'is_system' => true,
        ]);
    }

    public function manager(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Gérant',
            'slug' => 'manager',
            'weight' => 2,
            'is_system' => true,
        ]);
    }

    public function employee(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Employé',
            'slug' => 'employee',
            'weight' => 1,
            'is_system' => true,
        ]);
    }
}
