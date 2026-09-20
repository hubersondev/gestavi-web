<?php

namespace Database\Factories;

use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QuoteItem>
 */
class QuoteItemFactory extends Factory
{
    protected $model = QuoteItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quote_id' => Quote::factory(),
            'product_name' => fake()->randomElement(['Plateau de 30 œufs', 'Poulet vif']),
            'unit' => 'unité',
            'quantity' => fake()->numberBetween(1, 100),
            'unit_price' => fake()->numberBetween(500, 5000),
            'discount_percent' => 0,
        ];
    }
}
