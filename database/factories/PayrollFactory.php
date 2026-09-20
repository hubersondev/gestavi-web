<?php

namespace Database\Factories;

use App\Enums\PayrollStatus;
use App\Models\Employee;
use App\Models\Payroll;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payroll>
 */
class PayrollFactory extends Factory
{
    protected $model = Payroll::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'period_year' => now()->year,
            'period_month' => now()->month,
            'status' => PayrollStatus::Draft,
            'gross_salary' => fake()->numberBetween(75000, 600000),
            'worked_days' => 22,
        ];
    }
}
