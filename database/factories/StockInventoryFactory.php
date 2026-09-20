<?php

namespace Database\Factories;

use App\Enums\StockInventoryStatus;
use App\Models\Stock;
use App\Models\StockInventory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockInventory>
 */
class StockInventoryFactory extends Factory
{
    protected $model = StockInventory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stock_id' => Stock::factory(),
            'inventory_date' => now()->toDateString(),
            'theoretical_quantity' => 100,
            'physical_quantity' => 98,
            'difference' => -2,
            'unit_price' => 500,
            'status' => StockInventoryStatus::Draft,
        ];
    }
}
