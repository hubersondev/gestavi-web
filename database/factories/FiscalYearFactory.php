<?php

namespace Database\Factories;

use App\Enums\FiscalYearStatus;
use App\Models\FiscalYear;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalYear>
 */
class FiscalYearFactory extends Factory
{
    protected $model = FiscalYear::class;

    public function definition(): array
    {
        $year = fake()->unique()->numberBetween(2000, 2999);

        return [
            'organisation_id' => Organisation::factory(),
            'label' => "Exercice {$year}",
            'starts_on' => "{$year}-01-01",
            'ends_on' => "{$year}-12-31",
            'status' => FiscalYearStatus::Open,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (): array => ['status' => FiscalYearStatus::Closed, 'closed_at' => now(), 'net_result' => 0]);
    }
}
