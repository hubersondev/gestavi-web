<?php

namespace Database\Factories;

use App\Enums\TransactionCategory;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use Database\Factories\Concerns\InheritsOrganisationFromParent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    use InheritsOrganisationFromParent;

    protected $model = Transaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->numberBetween(1000, 500000);

        return [
            'organisation_id' => $this->organisationIdFrom('account_id', Account::class),
            'farm_id' => $this->farmIdFrom('account_id', Account::class),
            'account_id' => fn (array $attributes): int => Account::factory()->create($this->scopeAttributes($attributes))->id,
            'type' => TransactionType::Credit,
            'category' => TransactionCategory::Other,
            'amount' => $amount,
            'balance_before' => 0,
            'balance_after' => $amount,
            'description' => fake()->sentence(3),
            'transaction_date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
        ];
    }
}
