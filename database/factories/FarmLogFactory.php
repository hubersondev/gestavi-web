<?php

namespace Database\Factories;

use App\Enums\FarmLogCategory;
use App\Enums\FarmLogPriority;
use App\Models\Farm;
use App\Models\FarmLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FarmLog>
 */
class FarmLogFactory extends Factory
{
    protected $model = FarmLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'recorded_by' => User::factory(),
            'log_date' => fake()->dateTimeBetween('-10 days', 'now')->format('Y-m-d'),
            'title' => fake()->sentence(4),
            'category' => fake()->randomElement(FarmLogCategory::cases()),
            'priority' => FarmLogPriority::Normal,
            'description' => fake()->paragraph(),
        ];
    }
}
