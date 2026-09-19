<?php

namespace Database\Factories;

use App\Enums\SaleStatus;
use App\Models\Organisation;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'sale_date' => fake()->dateTimeBetween('-30 days', 'now'),
            'status' => SaleStatus::Draft,
            'subtotal' => 0,
            'total' => 0,
            'paid' => 0,
            'balance' => 0,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => SaleStatus::Pending]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SaleStatus::Paid,
            'paid' => $attributes['total'] ?? 0,
            'balance' => 0,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => SaleStatus::Cancelled]);
    }
}
