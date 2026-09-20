<?php

namespace Database\Factories;

use App\Enums\StockMovementReason;
use App\Enums\StockMovementType;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 100);
        $price = fake()->numberBetween(100, 5000);

        return [
            'stock_id' => Stock::factory(),
            'type' => StockMovementType::In,
            'reason' => StockMovementReason::Purchase,
            'quantity' => $quantity,
            'unit_price' => $price,
            'total_price' => $quantity * $price,
            'movement_date' => now()->toDateString(),
        ];
    }
}
