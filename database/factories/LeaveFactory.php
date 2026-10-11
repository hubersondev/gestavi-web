<?php

namespace Database\Factories;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Employee;
use App\Models\Leave;
use Database\Factories\Concerns\InheritsOrganisationFromParent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Leave>
 */
class LeaveFactory extends Factory
{
    use InheritsOrganisationFromParent;

    protected $model = Leave::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-30 days', '+30 days');
        $days = fake()->numberBetween(1, 10);

        return [
            'organisation_id' => $this->organisationIdFrom('employee_id', Employee::class),
            'farm_id' => $this->farmIdFrom('employee_id', Employee::class),
            'employee_id' => fn (array $attributes): int => Employee::factory()->create($this->scopeAttributes($attributes))->id,
            'type' => fake()->randomElement(LeaveType::cases()),
            'status' => LeaveStatus::cases()[0],
            'start_date' => $start->format('Y-m-d'),
            'end_date' => (clone $start)->modify("+{$days} days")->format('Y-m-d'),
            'days_count' => $days + 1,
            'reason' => fake()->sentence(),
        ];
    }
}
