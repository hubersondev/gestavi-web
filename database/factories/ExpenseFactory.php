<?php

namespace Database\Factories;

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        $amount = fake()->numberBetween(5000, 500000);

        return [
            'organisation_id' => Organisation::factory(),
            'category' => fake()->randomElement(ExpenseCategory::cases()),
            'description' => fake()->sentence(),
            'expense_date' => fake()->dateTimeBetween('-30 days', 'now'),
            'status' => ExpenseStatus::Unpaid,
            'amount' => $amount,
            'paid_amount' => 0,
            'balance' => $amount,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExpenseStatus::Paid,
            'paid_amount' => $attributes['amount'],
            'balance' => 0,
            'paid_at' => now(),
        ]);
    }
}
