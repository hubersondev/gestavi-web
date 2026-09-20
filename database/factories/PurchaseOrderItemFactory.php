<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Stock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    protected $model = PurchaseOrderItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 50);
        $price = fake()->numberBetween(1000, 20000);

        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'stock_id' => Stock::factory(),
            'product_name' => 'Aliment '.fake()->word(),
            'unit' => 'sac',
            'quantity_ordered' => $quantity,
            'quantity_received' => 0,
            'unit_price' => $price,
            'total' => $quantity * $price,
        ];
    }
}
