<?php

namespace Database\Factories;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Employee;
use App\Models\Leave;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Leave>
 */
class LeaveFactory extends Factory
{
    protected $model = Leave::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-30 days', '+30 days');
        $days = fake()->numberBetween(1, 10);

        return [
            'employee_id' => Employee::factory(),
            'type' => fake()->randomElement(LeaveType::cases()),
            'status' => LeaveStatus::cases()[0],
            'start_date' => $start->format('Y-m-d'),
            'end_date' => (clone $start)->modify("+{$days} days")->format('Y-m-d'),
            'days_count' => $days + 1,
            'reason' => fake()->sentence(),
        ];
    }
}
