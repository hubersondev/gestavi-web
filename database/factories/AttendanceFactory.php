<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'attendance_date' => fake()->unique()->dateTimeBetween('-60 days', 'now')->format('Y-m-d'),
            'status' => fake()->randomElement(AttendanceStatus::cases()),
            'check_in' => '07:30',
            'check_out' => '17:30',
            'late_minutes' => 0,
            'overtime_hours' => 0,
        ];
    }
}
