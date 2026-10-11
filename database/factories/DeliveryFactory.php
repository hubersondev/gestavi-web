<?php

namespace Database\Factories;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\Sale;
use Database\Factories\Concerns\InheritsOrganisationFromParent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Delivery>
 */
class DeliveryFactory extends Factory
{
    use InheritsOrganisationFromParent;

    protected $model = Delivery::class;

    public function definition(): array
    {
        return [
            'organisation_id' => $this->organisationIdFrom('sale_id', Sale::class),
            'farm_id' => $this->farmIdFrom('sale_id', Sale::class),
            'sale_id' => fn (array $attributes): int => Sale::factory()->create($this->scopeAttributes($attributes))->id,
            'delivery_date' => now()->toDateString(),
            'status' => DeliveryStatus::Planned,
            'driver_name' => fake()->name(),
            'vehicle' => strtoupper(fake()->bothify('?? ####')),
            'recipient_name' => fake()->name(),
            'recipient_phone' => '+225 07'.fake()->numerify('########'),
            'delivery_address' => fake()->address(),
        ];
    }

    public function delivered(): static
    {
        return $this->state(fn (): array => ['status' => DeliveryStatus::Delivered, 'delivered_at' => now()]);
    }
}
