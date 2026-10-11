<?php

namespace Database\Factories;

use App\Enums\PaymentTerms;
use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Database\Factories\Concerns\InheritsOrganisationFromParent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    use InheritsOrganisationFromParent;

    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'organisation_id' => $this->organisationIdFrom('supplier_id', Supplier::class),
            'farm_id' => $this->farmIdFrom('supplier_id', Supplier::class),
            'supplier_id' => fn (array $attributes): int => Supplier::factory()->create($this->scopeAttributes($attributes))->id,
            'order_date' => fake()->dateTimeBetween('-30 days', 'now'),
            'expected_delivery_date' => fake()->dateTimeBetween('now', '+15 days'),
            'status' => PurchaseOrderStatus::Draft,
            'payment_terms' => PaymentTerms::OnDelivery,
            'subtotal' => 0,
            'total' => 0,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => PurchaseOrderStatus::Approved]);
    }

    public function received(): static
    {
        return $this->state(fn () => ['status' => PurchaseOrderStatus::Received]);
    }
}
