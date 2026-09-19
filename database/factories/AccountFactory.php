<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'name' => fake()->randomElement(['Caisse principale', 'Banque SGBCI', 'Orange Money', 'Caisse secondaire']),
            'type' => fake()->randomElement(AccountType::cases()),
            'initial_balance' => fake()->numberBetween(0, 500000),
            'current_balance' => fn (array $attributes) => $attributes['initial_balance'],
            'currency' => 'XOF',
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function cash(): static
    {
        return $this->state(fn () => ['type' => AccountType::Cash, 'name' => 'Caisse']);
    }

    public function bank(): static
    {
        return $this->state(fn () => [
            'type' => AccountType::Bank,
            'name' => 'Banque '.fake()->randomElement(['SGBCI', 'NSIA', 'BOA']),
            'bank_name' => fake()->randomElement(['SGBCI', 'NSIA Banque', 'BOA']),
            'account_number' => fake()->bankAccountNumber(),
        ]);
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
