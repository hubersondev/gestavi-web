<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use Database\Factories\Concerns\InheritsOrganisationFromParent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attendance>
 */
class AttendanceFactory extends Factory
{
    use InheritsOrganisationFromParent;

    protected $model = Attendance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => $this->organisationIdFrom('employee_id', Employee::class),
            'farm_id' => $this->farmIdFrom('employee_id', Employee::class),
            'employee_id' => fn (array $attributes): int => Employee::factory()->create($this->scopeAttributes($attributes))->id,
            'attendance_date' => fake()->unique()->dateTimeBetween('-60 days', 'now')->format('Y-m-d'),
            'status' => fake()->randomElement(AttendanceStatus::cases()),
            'check_in' => '07:30',
            'check_out' => '17:30',
            'late_minutes' => 0,
            'overtime_hours' => 0,
        ];
    }
}
