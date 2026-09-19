<?php

use App\Enums\PlanFeature;
use App\Enums\StockMovementReason;
use App\Enums\StockMovementType;
use App\Filament\Farm\Resources\StockLots\Pages\CreateStockLot;
use App\Filament\Farm\Resources\StockMovements\Pages\CreateStockMovement;
use App\Filament\Farm\Resources\StockMovements\StockMovementResource;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;

/**
 * Les mouvements et lots créés depuis l'interface doivent passer par
 * Stock::recordIn / recordOut : la quantité du produit suit le journal.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);

    $plan = Plan::factory()->create([
        'features' => [
            PlanFeature::StockManagement->value,
            PlanFeature::StockBatches->value,
        ],
    ]);

    $this->organisation = Organisation::factory()->create(['plan_id' => $plan->id]);
    $ownerRole = $this->organisation->organisationRoles()->where('slug', 'owner')->first();

    $this->user = User::factory()->create();
    $this->organisation->addMember($this->user, $ownerRole);

    $this->stock = Stock::factory()->create([
        'organisation_id' => $this->organisation->id,
        'quantity' => 100,
        'unit_price' => 250,
    ]);

    actingAs($this->user);
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($this->organisation);
});

it('une entrée créée depuis le formulaire incrémente la quantité du produit', function () {
    Livewire::test(CreateStockMovement::class)
        ->fillForm([
            'stock_id' => $this->stock->id,
            'type' => StockMovementType::In->value,
            'reason' => StockMovementReason::Purchase->value,
            'quantity' => 40,
            'unit_price' => 300,
            'movement_date' => now()->subDay()->toDateString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect((float) $this->stock->fresh()->quantity)->toBe(140.0);

    $movement = StockMovement::query()->where('stock_id', $this->stock->id)->latest('id')->first();

    expect($movement->type)->toBe(StockMovementType::In)
        ->and((float) $movement->total_price)->toBe(12000.0)
        ->and($movement->movement_date->toDateString())->toBe(now()->subDay()->toDateString());
});

it('une sortie créée depuis le formulaire décrémente la quantité du produit', function () {
    Livewire::test(CreateStockMovement::class)
        ->fillForm([
            'stock_id' => $this->stock->id,
            'type' => StockMovementType::Out->value,
            'reason' => StockMovementReason::Consumption->value,
            'quantity' => 30,
            'movement_date' => now()->toDateString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect((float) $this->stock->fresh()->quantity)->toBe(70.0);
});

it('une sortie supérieure au stock disponible est refusée sans créer de mouvement', function () {
    Livewire::test(CreateStockMovement::class)
        ->fillForm([
            'stock_id' => $this->stock->id,
            'type' => StockMovementType::Out->value,
            'reason' => StockMovementReason::Consumption->value,
            'quantity' => 500,
            'movement_date' => now()->toDateString(),
        ])
        ->call('create');

    expect((float) $this->stock->fresh()->quantity)->toBe(100.0)
        ->and(StockMovement::query()->where('stock_id', $this->stock->id)->count())->toBe(0);
});

it('la réception d\'un lot crée l\'entrée de stock correspondante', function () {
    Livewire::test(CreateStockLot::class)
        ->fillForm([
            'stock_id' => $this->stock->id,
            'lot_number' => 'LOT-2026-001',
            'reception_date' => now()->toDateString(),
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'initial_quantity' => 25,
            'unit_price' => 200,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $stock = $this->stock->fresh();
    $lot = $stock->lots()->first();

    expect((float) $stock->quantity)->toBe(125.0)
        ->and($lot)->not->toBeNull()
        ->and((float) $lot->current_quantity)->toBe(25.0)
        ->and($stock->movements()->where('stock_lot_id', $lot->id)->count())->toBe(1);
});

it('les mouvements de stock ne sont ni modifiables ni supprimables', function () {
    $movement = $this->stock->recordIn(10);

    expect(StockMovementResource::canEdit($movement))->toBeFalse()
        ->and(StockMovementResource::canDelete($movement))->toBeFalse()
        ->and(StockMovementResource::canDeleteAny())->toBeFalse();
});
