<?php

namespace Database\Factories;

use App\Enums\StockInventoryStatus;
use App\Models\Stock;
use App\Models\StockInventory;
use Database\Factories\Concerns\InheritsOrganisationFromParent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockInventory>
 */
class StockInventoryFactory extends Factory
{
    use InheritsOrganisationFromParent;

    protected $model = StockInventory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => $this->organisationIdFrom('stock_id', Stock::class),
            'farm_id' => $this->farmIdFrom('stock_id', Stock::class),
            'stock_id' => fn (array $attributes): int => Stock::factory()->create($this->scopeAttributes($attributes))->id,
            'inventory_date' => now()->toDateString(),
            'theoretical_quantity' => 100,
            'physical_quantity' => 98,
            'difference' => -2,
            'unit_price' => 500,
            'status' => StockInventoryStatus::Draft,
        ];
    }
}
