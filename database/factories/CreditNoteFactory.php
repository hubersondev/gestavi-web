<?php

namespace Database\Factories;

use App\Enums\CreditNoteStatus;
use App\Models\CreditNote;
use App\Models\Sale;
use Database\Factories\Concerns\InheritsOrganisationFromParent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditNote>
 */
class CreditNoteFactory extends Factory
{
    use InheritsOrganisationFromParent;

    protected $model = CreditNote::class;

    public function definition(): array
    {
        return [
            'organisation_id' => $this->organisationIdFrom('sale_id', Sale::class),
            'farm_id' => $this->farmIdFrom('sale_id', Sale::class),
            'sale_id' => fn (array $attributes): int => Sale::factory()->create($this->scopeAttributes($attributes))->id,
            'issue_date' => fake()->dateTimeBetween('-15 days', 'now'),
            'amount' => fake()->numberBetween(1000, 50000),
            'reason' => fake()->randomElement(['Produit retourné', 'Erreur de facturation', 'Remise commerciale']),
            'status' => CreditNoteStatus::Draft,
        ];
    }

    public function issued(): static
    {
        return $this->state(fn () => ['status' => CreditNoteStatus::Issued]);
    }

    public function applied(): static
    {
        return $this->state(fn () => ['status' => CreditNoteStatus::Applied, 'applied_at' => now()]);
    }
}
