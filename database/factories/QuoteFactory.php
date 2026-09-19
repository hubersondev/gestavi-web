<?php

namespace Database\Factories;

use App\Enums\QuoteStatus;
use App\Models\Organisation;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'quote_date' => fake()->dateTimeBetween('-30 days', 'now'),
            'valid_until' => fake()->dateTimeBetween('now', '+30 days'),
            'status' => QuoteStatus::Draft,
            'subtotal' => 0,
            'total' => 0,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['status' => QuoteStatus::Accepted]);
    }

    public function sent(): static
    {
        return $this->state(fn () => ['status' => QuoteStatus::Sent]);
    }
}
