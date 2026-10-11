<?php

namespace Database\Factories;

use App\Enums\PayrollStatus;
use App\Models\Employee;
use App\Models\Payroll;
use Database\Factories\Concerns\InheritsOrganisationFromParent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payroll>
 */
class PayrollFactory extends Factory
{
    use InheritsOrganisationFromParent;

    protected $model = Payroll::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => $this->organisationIdFrom('employee_id', Employee::class),
            'farm_id' => $this->farmIdFrom('employee_id', Employee::class),
            'employee_id' => fn (array $attributes): int => Employee::factory()->create($this->scopeAttributes($attributes))->id,
            'period_year' => now()->year,
            'period_month' => now()->month,
            'status' => PayrollStatus::Draft,
            'gross_salary' => fake()->numberBetween(75000, 600000),
            'worked_days' => 22,
        ];
    }
}
