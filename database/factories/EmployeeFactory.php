<?php

namespace Database\Factories;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'farm_id' => null,
            'employee_number' => 'EMP-'.fake()->unique()->numerify('#####'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'hire_date' => fake()->dateTimeBetween('-3 years', '-1 month'),
            'status' => EmployeeStatus::Active,
            'fiscal_parts' => 1,
        ];
    }

    public function terminated(): static
    {
        return $this->state(fn () => [
            'status' => EmployeeStatus::Terminated,
            'termination_date' => now(),
        ]);
    }
}
