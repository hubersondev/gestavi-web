<?php

namespace Database\Factories;

use App\Enums\StockCategoryType;
use App\Models\Organisation;
use App\Models\StockCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockCategory>
 */
class StockCategoryFactory extends Factory
{
    protected $model = StockCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'organisation_id' => Organisation::factory(),
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'type' => fake()->randomElement(StockCategoryType::cases()),
        ];
    }
}
