<?php

namespace Database\Factories;

use App\Enums\BillingCycle;
use App\Enums\SubscriptionStatus;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'plan_id' => Plan::factory(),
            'plan_name' => fn (array $attributes): string => Plan::query()->whereKey($attributes['plan_id'])->value('name') ?? 'Plan',
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => BillingCycle::Monthly,
            'price' => fn (array $attributes): float => (float) (Plan::query()->whereKey($attributes['plan_id'])->value('monthly_price') ?? 0),
            'currency' => 'XOF',
            'starts_at' => today()->startOfMonth()->toDateString(),
            'ends_at' => today()->startOfMonth()->addMonth()->subDay()->toDateString(),
        ];
    }

    public function trial(int $days = 14): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::Trial,
            'price' => 0,
            'starts_at' => today()->toDateString(),
            'ends_at' => today()->addDays($days)->toDateString(),
            'trial_ends_at' => today()->addDays($days)->toDateString(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::Expired,
            'starts_at' => today()->subMonths(2)->toDateString(),
            'ends_at' => today()->subMonth()->toDateString(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Annulé par le client',
        ]);
    }

    /** Échéance dans $days jours. */
    public function endingIn(int $days): static
    {
        return $this->state(fn (): array => [
            'starts_at' => today()->addDays($days)->subMonth()->addDay()->toDateString(),
            'ends_at' => today()->addDays($days)->toDateString(),
        ]);
    }
}
