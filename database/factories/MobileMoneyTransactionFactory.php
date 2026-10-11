<?php

namespace Database\Factories;

use App\Enums\MobileMoneyOperator;
use App\Models\Account;
use App\Models\MobileMoneyTransaction;
use App\Models\Organisation;
use App\Services\MobileMoney\Direction;
use App\Services\MobileMoney\TransactionStatus;
use Database\Factories\Concerns\InheritsOrganisationFromParent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MobileMoneyTransaction>
 */
class MobileMoneyTransactionFactory extends Factory
{
    use InheritsOrganisationFromParent;

    protected $model = MobileMoneyTransaction::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'farm_id' => null,
            'account_id' => fn (array $attributes): int => Account::factory()->create($this->scopeAttributes($attributes))->id,
            'operator' => fake()->randomElement(MobileMoneyOperator::cases()),
            'external_id' => strtoupper(fake()->unique()->bothify('MM-########')),
            'direction' => Direction::In,
            'amount' => fake()->numberBetween(1000, 500000),
            'currency' => 'XOF',
            'payer_phone' => '+225 07'.fake()->numerify('########'),
            'payer_name' => fake()->name(),
            'reference' => null,
            'occurred_at' => now()->subMinutes(fake()->numberBetween(1, 600)),
            'status' => TransactionStatus::Received,
            'raw_payload' => [],
        ];
    }
}
