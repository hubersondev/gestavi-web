<?php

namespace Database\Factories;

use App\Enums\LedgerAnchorReason;
use App\Models\LedgerAnchor;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LedgerAnchor>
 */
class LedgerAnchorFactory extends Factory
{
    protected $model = LedgerAnchor::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'head_entry_id' => null,
            'head_hash' => hash('sha256', $this->faker->uuid()),
            'entry_count' => $this->faker->numberBetween(0, 500),
            'reason' => LedgerAnchorReason::Manual,
            'is_valid' => true,
            'anchored_by' => null,
            'notes' => null,
        ];
    }
}
