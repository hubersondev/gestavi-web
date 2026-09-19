<?php

namespace Database\Factories;

use App\Enums\PaymentTerms;
use App\Enums\PurchaseOrderStatus;
use App\Models\Organisation;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'supplier_id' => Supplier::factory(),
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
