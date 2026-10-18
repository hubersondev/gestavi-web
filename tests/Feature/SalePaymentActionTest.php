<?php

use App\Enums\PaymentMethod;
use App\Enums\PlanFeature;
use App\Enums\SaleStatus;
use App\Filament\Farm\Resources\Sales\Pages\ViewSale;
use App\Models\Account;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\User;
use App\Services\SaleService;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * Le Select « Mode de paiement » est construit sur l'énumération PaymentMethod : son état peut
 * contenir le cas lui-même, que l'action doit accepter tel quel (régression du 18/10/2026).
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);

    $plan = Plan::factory()->create(['features' => collect(PlanFeature::cases())->map->value->all()]);
    $this->organisation = Organisation::factory()->create(['plan_id' => $plan->id]);
    $this->owner = User::factory()->create();
    $this->organisation->addMember($this->owner, 'owner');

    actingAs($this->owner);
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($this->organisation);

    $this->account = Account::factory()->cash()->create(['organisation_id' => $this->organisation->id, 'initial_balance' => 0, 'current_balance' => 0]);
    $this->sale = app(SaleService::class)->createSale(
        data: ['organisation_id' => $this->organisation->id, 'sale_date' => now()->toDateString()],
        items: [['product_name' => 'Poulets', 'unit' => 'unité', 'quantity' => 10, 'unit_price' => 3500]],
    );
});

it('enregistre le paiement d\'une facture depuis sa fiche, mode de paiement en énumération ou en valeur', function (PaymentMethod|string $method) {
    Livewire::test(ViewSale::class, ['record' => $this->sale->getRouteKey()])
        ->callAction('recordPayment', data: [
            'amount' => 35000,
            'method' => $method,
            'account_id' => $this->account->id,
            'payment_date' => now()->toDateString(),
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Paiement enregistré');

    $sale = $this->sale->fresh();

    expect($sale->status)->toBe(SaleStatus::Paid)
        ->and($sale->payments()->sole()->method)->toBe(PaymentMethod::Wave)
        ->and((float) $this->account->fresh()->current_balance)->toBe(35000.0);
})->with([
    'cas de l\'énumération' => [PaymentMethod::Wave],
    'valeur' => ['wave'],
]);
