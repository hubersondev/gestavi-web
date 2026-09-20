<?php

namespace Database\Factories;

use App\Enums\AccountTransferStatus;
use App\Models\Account;
use App\Models\AccountTransfer;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AccountTransfer>
 */
class AccountTransferFactory extends Factory
{
    protected $model = AccountTransfer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $organisation = Organisation::factory()->create();

        return [
            'organisation_id' => $organisation->id,
            'from_account_id' => Account::factory()->create(['organisation_id' => $organisation->id, 'farm_id' => null])->id,
            'to_account_id' => Account::factory()->create(['organisation_id' => $organisation->id, 'farm_id' => null])->id,
            'amount' => fake()->numberBetween(10000, 300000),
            'fees' => 0,
            'transfer_date' => now()->toDateString(),
            'status' => AccountTransferStatus::cases()[0],
        ];
    }
}
