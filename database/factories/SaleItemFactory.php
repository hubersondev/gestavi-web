<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SaleItem>
 */
class SaleItemFactory extends Factory
{
    protected $model = SaleItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'product_name' => fake()->randomElement(['Plateau de 30 œufs', 'Poulet vif', 'Poulet abattu']),
            'unit' => 'unité',
            'quantity' => fake()->numberBetween(1, 100),
            'unit_price' => fake()->numberBetween(500, 5000),
            'discount_percent' => 0,
        ];
    }
}
