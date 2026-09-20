<?php

namespace Database\Factories;

use App\Enums\StockLotStatus;
use App\Models\Stock;
use App\Models\StockLot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockLot>
 */
class StockLotFactory extends Factory
{
    protected $model = StockLot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(10, 500);

        return [
            'stock_id' => Stock::factory(),
            'lot_number' => strtoupper(fake()->unique()->bothify('LOT-####-??')),
            'reception_date' => now()->subDays(fake()->numberBetween(0, 30))->toDateString(),
            'expiry_date' => now()->addMonths(fake()->numberBetween(1, 12))->toDateString(),
            'initial_quantity' => $quantity,
            'current_quantity' => $quantity,
            'unit_price' => fake()->numberBetween(100, 5000),
            'status' => StockLotStatus::Available,
        ];
    }
}
