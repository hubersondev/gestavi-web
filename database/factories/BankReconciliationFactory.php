<?php

namespace Database\Factories;

use App\Enums\BankReconciliationStatus;
use App\Models\Account;
use App\Models\BankReconciliation;
use Database\Factories\Concerns\InheritsOrganisationFromParent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankReconciliation>
 */
class BankReconciliationFactory extends Factory
{
    use InheritsOrganisationFromParent;

    protected $model = BankReconciliation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => $this->organisationIdFrom('account_id', Account::class),
            'farm_id' => $this->farmIdFrom('account_id', Account::class),
            'account_id' => fn (array $attributes): int => Account::factory()->bank()->create($this->scopeAttributes($attributes))->id,
            'statement_date' => now()->subDays(fake()->numberBetween(1, 30))->toDateString(),
            'statement_balance' => fake()->numberBetween(100000, 5000000),
            'status' => BankReconciliationStatus::Draft,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => BankReconciliationStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
