<?php

use App\Enums\PaymentMethod;
use App\Enums\PlanFeature;
use App\Enums\SaleStatus;
use App\Exceptions\CommercialException;
use App\Filament\Farm\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Farm\Resources\Quotes\Pages\CreateQuote;
use App\Filament\Farm\Resources\Sales\Pages\CreateSale;
use App\Filament\Farm\Resources\Sales\Pages\EditSale;
use App\Filament\Farm\Resources\Sales\SaleResource;
use App\Models\Customer;
use App\Models\Farm;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\User;
use App\Services\SaleService;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;

/**
 * Les lignes (Repeater `items`) sont saisies dans le formulaire puis persistées
 * par les services : la création et l'édition doivent fonctionner de bout en bout.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);

    $plan = Plan::factory()->create([
        'features' => collect(PlanFeature::cases())->map->value->all(),
    ]);

    $this->organisation = Organisation::factory()->create(['plan_id' => $plan->id]);
    $ownerRole = $this->organisation->organisationRoles()->where('slug', 'owner')->first();

    $this->user = User::factory()->create();
    $this->organisation->addMember($this->user, $ownerRole);

    $this->farm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);

    actingAs($this->user);
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($this->organisation);
});

it('crée une facture avec ses lignes depuis le formulaire', function () {
    $customer = Customer::factory()->create(['organisation_id' => $this->organisation->id]);

    Livewire::test(CreateSale::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'sale_date' => now()->toDateString(),
            'status' => SaleStatus::Pending->value,
        ])
        ->set('data.items', [
            ['product_name' => 'Poulets vifs', 'unit' => 'sujet', 'quantity' => 20, 'unit_price' => 3500, 'discount_percent' => 0],
            ['product_name' => 'Œufs', 'unit' => 'plateau', 'quantity' => 10, 'unit_price' => 3000, 'discount_percent' => 10],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $sale = Sale::query()->where('organisation_id', $this->organisation->id)->first();

    expect($sale)->not->toBeNull()
        ->and($sale->items()->count())->toBe(2)
        // 20 × 3 500 = 70 000 ; 10 × 3 000 − 10 % = 27 000
        ->and((float) $sale->subtotal)->toBe(97000.0)
        ->and((float) $sale->total)->toBe(97000.0)
        ->and((float) $sale->balance)->toBe(97000.0)
        ->and($sale->status)->toBe(SaleStatus::Pending)
        ->and((float) $customer->fresh()->balance)->toBe(97000.0);
});

it('refuse une facture sans ligne', function () {
    Livewire::test(CreateSale::class)
        ->fillForm([
            'sale_date' => now()->toDateString(),
            'items' => [],
        ])
        ->call('create');

    expect(Sale::query()->count())->toBe(0);
});

it('crée un devis avec ses lignes depuis le formulaire', function () {
    Livewire::test(CreateQuote::class)
        ->fillForm([
            'quote_date' => now()->toDateString(),
            'valid_until' => now()->addDays(15)->toDateString(),
            'client_name' => 'Restaurant Le Baobab',
        ])
        ->set('data.items', [
            ['product_name' => 'Poulets vifs', 'unit' => 'sujet', 'quantity' => 50, 'unit_price' => 3200, 'discount_percent' => 0],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $quote = Quote::query()->where('organisation_id', $this->organisation->id)->first();

    expect($quote)->not->toBeNull()
        ->and($quote->items()->count())->toBe(1)
        ->and((float) $quote->total)->toBe(160000.0);
});

it('crée un bon de commande avec ses lignes depuis le formulaire', function () {
    $supplier = Supplier::factory()->create(['farm_id' => $this->farm->id]);
    $stock = Stock::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'unit' => 'sac']);

    Livewire::test(CreatePurchaseOrder::class)
        ->fillForm([
            'supplier_id' => $supplier->id,
            'farm_id' => $this->farm->id,
            'order_date' => now()->toDateString(),
        ])
        ->set('data.items', [
            ['stock_id' => $stock->id, 'product_name' => $stock->name, 'unit' => 'sac', 'quantity_ordered' => 40, 'unit_price' => 15000],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $order = PurchaseOrder::query()->where('organisation_id', $this->organisation->id)->first();

    expect($order)->not->toBeNull()
        ->and($order->items()->count())->toBe(1)
        ->and((float) $order->total)->toBe(600000.0);
});

it('modifie les lignes d\'une facture non payée depuis le formulaire d\'édition', function () {
    $sale = app(SaleService::class)->createSale(
        ['organisation_id' => $this->organisation->id, 'sale_date' => now()->toDateString()],
        [['product_name' => 'Poulets vifs', 'unit' => 'sujet', 'quantity' => 10, 'unit_price' => 3000]],
    );

    $existingId = $sale->items()->first()->id;

    Livewire::test(EditSale::class, ['record' => $sale->getRouteKey()])
        ->set('data.items', [
            ['id' => $existingId, 'product_name' => 'Poulets vifs', 'unit' => 'sujet', 'quantity' => 15, 'unit_price' => 3000, 'discount_percent' => 0],
            ['product_name' => 'Fumier', 'unit' => 'sac', 'quantity' => 4, 'unit_price' => 2500, 'discount_percent' => 0],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $sale->refresh();

    expect($sale->items()->count())->toBe(2)
        ->and($sale->items()->whereKey($existingId)->exists())->toBeTrue()
        ->and((float) $sale->items()->whereKey($existingId)->value('quantity'))->toBe(15.0)
        ->and((float) $sale->total)->toBe(55000.0);
});

it('verrouille une facture dès qu\'un paiement existe', function () {
    $sale = app(SaleService::class)->createSale(
        ['organisation_id' => $this->organisation->id, 'sale_date' => now()->toDateString()],
        [['product_name' => 'Œufs', 'unit' => 'plateau', 'quantity' => 10, 'unit_price' => 3000]],
    );

    expect(SaleResource::canEdit($sale))->toBeTrue();

    $sale->addPayment(10000, PaymentMethod::Cash);

    expect(SaleResource::canEdit($sale->fresh()))->toBeFalse();

    expect(fn () => app(SaleService::class)->syncSaleItems($sale->fresh(), [
        ['product_name' => 'Œufs', 'unit' => 'plateau', 'quantity' => 99, 'unit_price' => 3000],
    ]))->toThrow(CommercialException::class);
});
