<?php

namespace Database\Factories;

use App\Enums\StockMovementReason;
use App\Enums\StockMovementType;
use App\Models\Stock;
use App\Models\StockMovement;
use Database\Factories\Concerns\InheritsOrganisationFromParent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockMovement>
 */
class StockMovementFactory extends Factory
{
    use InheritsOrganisationFromParent;

    protected $model = StockMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 100);
        $price = fake()->numberBetween(100, 5000);

        return [
            'organisation_id' => $this->organisationIdFrom('stock_id', Stock::class),
            'farm_id' => $this->farmIdFrom('stock_id', Stock::class),
            'stock_id' => fn (array $attributes): int => Stock::factory()->create($this->scopeAttributes($attributes))->id,
            'type' => StockMovementType::In,
            'reason' => StockMovementReason::Purchase,
            'quantity' => $quantity,
            'unit_price' => $price,
            'total_price' => $quantity * $price,
            'movement_date' => now()->toDateString(),
        ];
    }
}
