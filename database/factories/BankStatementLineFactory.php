<?php

namespace Database\Factories;

use App\Enums\BankStatementLineStatus;
use App\Models\BankReconciliation;
use App\Models\BankStatementLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankStatementLine>
 */
class BankStatementLineFactory extends Factory
{
    protected $model = BankStatementLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bank_reconciliation_id' => BankReconciliation::factory(),
            'line_date' => now()->subDays(fake()->numberBetween(1, 30))->toDateString(),
            'label' => fake()->sentence(3),
            'amount' => fake()->numberBetween(-500000, 500000),
            'reference' => fake()->optional()->bothify('REF-####'),
            'status' => BankStatementLineStatus::Unmatched,
        ];
    }
}
