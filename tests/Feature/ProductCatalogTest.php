<?php

use App\Enums\PlanFeature;
use App\Enums\ProductKind;
use App\Filament\Farm\Resources\Products\Pages\CreateProduct;
use App\Filament\Farm\Resources\Sales\Pages\CreateSale;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\Product;
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

    actingAs($this->user);
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($this->organisation);
});

it('crée le catalogue par défaut à la création d\'une organisation', function () {
    $products = Product::query()->where('organisation_id', $this->organisation->id)->get();

    expect($products)->toHaveCount(5)
        ->and($products->firstWhere('kind', ProductKind::LiveBird)->is_live_birds)->toBeTrue()
        ->and($products->firstWhere('kind', ProductKind::EggsTray)->unit)->toBe('plateau');

    // Idempotent
    $this->organisation->seedDefaultProducts();
    expect(Product::query()->where('organisation_id', $this->organisation->id)->count())->toBe(5);
});

it('permet de créer un produit depuis le formulaire', function () {
    Livewire::test(CreateProduct::class)
        ->fillForm([
            'name' => 'Plateau de 12 œufs',
            'kind' => ProductKind::EggsTray->value,
            'unit' => 'plateau',
            'default_unit_price' => 1500,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Product::query()->where('organisation_id', $this->organisation->id)->where('name', 'Plateau de 12 œufs')->exists())->toBeTrue();
});

it('un produit du catalogue pré-remplit la ligne de vente et se retrouve sur la facture', function () {
    $product = Product::query()->where('organisation_id', $this->organisation->id)->where('kind', ProductKind::LiveBird)->first();

    $component = Livewire::test(CreateSale::class)
        ->fillForm(['sale_date' => now()->toDateString(), 'status' => 'pending'])
        ->set('data.items', [[
            'product_id' => $product->id,
            'product_name' => '',
            'quantity' => 10,
            'unit_price' => 0,
            'unit' => 'unité',
            'discount_percent' => 0,
            'is_live_birds' => false,
        ]]);

    // Le pré-remplissage se déclenche à la sélection du produit
    $component->set('data.items.'.array_key_first($component->get('data.items')).'.product_id', $product->id);

    $items = $component->get('data.items');
    $line = $items[array_key_first($items)];

    expect($line['product_name'])->toBe('Poulet vif')
        ->and((float) $line['unit_price'])->toBe(3500.0)
        ->and($line['unit'])->toBe('sujet')
        ->and((bool) $line['is_live_birds'])->toBeTrue();

    $component->call('create')->assertHasNoFormErrors();

    $sale = Sale::query()->where('organisation_id', $this->organisation->id)->latest('id')->first();

    expect($sale->items()->count())->toBe(1)
        ->and($sale->items()->first()->product_id)->toBe($product->id)
        ->and((float) $sale->subtotal)->toBe(35000.0);
});
