<?php

namespace Database\Factories;

use App\Enums\TransactionCategory;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->numberBetween(1000, 500000);

        return [
            'account_id' => Account::factory(),
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
