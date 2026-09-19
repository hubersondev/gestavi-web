<?php

namespace Database\Factories;

use App\Enums\UserType;
use App\Models\PlatformRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'type' => UserType::Farmer,
            'is_active' => true,
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->e164PhoneNumber(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => UserType::System,
        ])->afterCreating(function (User $user) {
            $role = PlatformRole::firstOrCreate(
                ['slug' => 'super-admin'],
                ['name' => 'Super Admin', 'description' => 'Accès total à la plateforme', 'is_system' => true],
            );
            $user->platformRoles()->sync([$role->id]);
        });
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => UserType::System,
        ])->afterCreating(function (User $user) {
            $role = PlatformRole::firstOrCreate(
                ['slug' => 'admin'],
                ['name' => 'Admin', 'description' => 'Administration de la plateforme', 'is_system' => true],
            );
            $user->platformRoles()->sync([$role->id]);
        });
    }

    public function platformUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => UserType::System,
        ])->afterCreating(function (User $user) {
            $role = PlatformRole::firstOrCreate(
                ['slug' => 'user'],
                ['name' => 'Utilisateur', 'description' => 'Utilisateur standard de la plateforme', 'is_system' => true],
            );
            $user->platformRoles()->sync([$role->id]);
        });
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
