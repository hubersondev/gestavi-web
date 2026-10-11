<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\Sale;
use Database\Factories\Concerns\InheritsOrganisationFromParent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    use InheritsOrganisationFromParent;

    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => $this->organisationIdFrom('sale_id', Sale::class),
            'farm_id' => $this->farmIdFrom('sale_id', Sale::class),
            'sale_id' => fn (array $attributes): int => Sale::factory()->create($this->scopeAttributes($attributes))->id,
            'amount' => fake()->numberBetween(1000, 100000),
            'method' => PaymentMethod::Cash,
            'payment_date' => now()->toDateString(),
        ];
    }
}
