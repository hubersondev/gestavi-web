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
        // Les comptes suivent l'organisation (et la ferme) passées en attributs, sinon une organisation neuve
        $accountFor = fn (array $attributes): int => Account::factory()->create([
            'organisation_id' => $attributes['organisation_id'],
            'farm_id' => $attributes['farm_id'] ?? null,
        ])->id;

        return [
            'organisation_id' => Organisation::factory(),
            'from_account_id' => $accountFor,
            'to_account_id' => $accountFor,
            'amount' => fake()->numberBetween(10000, 300000),
            'fees' => 0,
            'transfer_date' => now()->toDateString(),
            'status' => AccountTransferStatus::cases()[0],
        ];
    }
}
