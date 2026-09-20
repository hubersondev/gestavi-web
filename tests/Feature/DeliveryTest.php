<?php

use App\Enums\DeliveryStatus;
use App\Enums\PlanFeature;
use App\Filament\Farm\Resources\Deliveries\DeliveryResource;
use App\Filament\Farm\Resources\Deliveries\Pages\CreateDelivery;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Farm;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);

    $plan = Plan::factory()->create(['features' => collect(PlanFeature::cases())->map->value->all()]);
    $this->organisation = Organisation::factory()->create(['plan_id' => $plan->id]);
    $this->user = User::factory()->create();
    $this->organisation->addMember($this->user, $this->organisation->organisationRoles()->where('slug', 'owner')->first());

    $this->farm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);
    $this->customer = Customer::factory()->create(['organisation_id' => $this->organisation->id, 'name' => 'Marché de Cocody', 'phone' => '+225 07 00 00 00 01', 'address' => 'Cocody, Abidjan']);
    $this->sale = Sale::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'customer_id' => $this->customer->id, 'status' => 'pending']);
    $this->sale->items()->create(['product_name' => 'Plateau de 30 œufs', 'quantity' => 20, 'unit_price' => 3000, 'unit' => 'plateau']);

    actingAs($this->user);
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($this->organisation);
});

it('numérote les bons de livraison BL-AAAA-00001 par organisation et hérite ferme et organisation de la facture', function () {
    $delivery = Delivery::create(['sale_id' => $this->sale->id, 'delivery_date' => now()->toDateString()]);
    $second = Delivery::create(['sale_id' => $this->sale->id, 'delivery_date' => now()->toDateString()]);

    $year = now()->year;

    expect($delivery->delivery_number)->toBe("BL-{$year}-00001")
        ->and($second->delivery_number)->toBe("BL-{$year}-00002")
        ->and($delivery->organisation_id)->toBe($this->organisation->id)
        ->and($delivery->farm_id)->toBe($this->farm->id)
        ->and($delivery->status)->toBe(DeliveryStatus::Planned)
        ->and($delivery->created_by)->toBe($this->user->id);
});

it('suit le cycle planifiée → en cours → livrée et verrouille l\'édition', function () {
    $delivery = Delivery::create(['sale_id' => $this->sale->id, 'delivery_date' => now()->toDateString()]);

    expect(DeliveryResource::canEdit($delivery))->toBeTrue();

    $delivery->markInTransit();
    expect($delivery->fresh()->status)->toBe(DeliveryStatus::InTransit);

    $delivery->markDelivered('Mme Koné', 'Signé à la réception');
    $delivery->refresh();

    expect($delivery->status)->toBe(DeliveryStatus::Delivered)
        ->and($delivery->delivered_at)->not->toBeNull()
        ->and($delivery->recipient_name)->toBe('Mme Koné')
        ->and(DeliveryResource::canEdit($delivery))->toBeFalse();
});

it('la page de création est pré-remplie depuis la facture et enregistre la livraison', function () {
    Livewire::withQueryParams(['sale' => $this->sale->id])
        ->test(CreateDelivery::class)
        ->assertFormSet(['recipient_name' => 'Marché de Cocody', 'delivery_address' => 'Cocody, Abidjan'])
        ->fillForm([
            'sale_id' => $this->sale->id,
            'delivery_date' => now()->toDateString(),
            'driver_name' => 'Yao',
            'vehicle' => 'Tricycle AB-123',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $delivery = Delivery::query()->where('sale_id', $this->sale->id)->first();

    expect($delivery)->not->toBeNull()
        ->and($delivery->driver_name)->toBe('Yao')
        ->and($delivery->delivery_number)->toStartWith('BL-');
});

it('génère le bon de livraison PDF', function () {
    $delivery = Delivery::create(['sale_id' => $this->sale->id, 'delivery_date' => now()->toDateString(), 'driver_name' => 'Yao']);

    $html = view('pdf.delivery-note', ['delivery' => $delivery->load(['sale.items', 'sale.customer', 'sale.organisation', 'farm'])])->render();

    expect($html)->toContain('BON DE LIVRAISON')
        ->toContain($delivery->delivery_number)
        ->toContain('Plateau de 30 œufs')
        ->toContain('Marché de Cocody');
});
