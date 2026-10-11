<?php

namespace Database\Factories;

use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Models\Contract;
use App\Models\Employee;
use Database\Factories\Concerns\InheritsOrganisationFromParent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    use InheritsOrganisationFromParent;

    protected $model = Contract::class;

    public function definition(): array
    {
        return [
            'organisation_id' => $this->organisationIdFrom('employee_id', Employee::class),
            'farm_id' => $this->farmIdFrom('employee_id', Employee::class),
            'employee_id' => fn (array $attributes): int => Employee::factory()->create($this->scopeAttributes($attributes))->id,
            'type' => ContractType::Cdi,
            'status' => ContractStatus::Active,
            'start_date' => now()->subYear()->startOfMonth(),
            'end_date' => null,
            'base_salary' => 150_000,
            'transport_allowance' => 0,
            'housing_allowance' => 0,
            'food_allowance' => 0,
            'working_hours_per_week' => 40,
            'annual_leave_days' => 26,
            'position' => fake()->jobTitle(),
        ];
    }

    public function cdd(): static
    {
        return $this->state(fn () => [
            'type' => ContractType::Cdd,
            'end_date' => now()->addYear(),
        ]);
    }
}
