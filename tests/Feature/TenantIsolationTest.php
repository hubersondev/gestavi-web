<?php

use App\Filament\Farm\Resources\Sales\SaleResource;
use App\Models\Organisation;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);

    $this->orgA = Organisation::factory()->create();
    $this->orgB = Organisation::factory()->create();

    $ownerRoleA = $this->orgA->organisationRoles()->where('slug', 'owner')->first();

    $this->userA = User::factory()->create();
    $this->orgA->addMember($this->userA, $ownerRoleA);

    actingAs($this->userA);
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($this->orgA);
});

it('le scope tenant filtre les Sale d\'une autre organisation', function () {
    $saleA = Sale::factory()->create(['organisation_id' => $this->orgA->id]);
    $saleB = Sale::factory()->create(['organisation_id' => $this->orgB->id]);

    $results = SaleResource::scopeEloquentQueryToTenant(Sale::query(), $this->orgA)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($saleA->id)
        ->and($results->pluck('id'))->not->toContain($saleB->id);
});

it('un owner de l\'org A ne voit aucune Sale de l\'org B', function () {
    Sale::factory()->count(3)->create(['organisation_id' => $this->orgB->id]);
    Sale::factory()->count(2)->create(['organisation_id' => $this->orgA->id]);

    $results = SaleResource::scopeEloquentQueryToTenant(Sale::query(), $this->orgA)->get();

    expect($results)->toHaveCount(2)
        ->and($results->every(fn (Sale $s) => $s->organisation_id === $this->orgA->id))->toBeTrue();
});

it('la policy bloque la mise à jour d\'une Sale appartenant à une autre organisation', function () {
    $foreignSale = Sale::factory()->create(['organisation_id' => $this->orgB->id]);

    expect($this->userA->can('update', $foreignSale))->toBeFalse()
        ->and($this->userA->can('view', $foreignSale))->toBeFalse()
        ->and($this->userA->can('delete', $foreignSale))->toBeFalse();
});

it('la policy autorise la mise à jour d\'une Sale de sa propre organisation', function () {
    $ownSale = Sale::factory()->create(['organisation_id' => $this->orgA->id]);

    expect($this->userA->can('update', $ownSale))->toBeTrue()
        ->and($this->userA->can('view', $ownSale))->toBeTrue();
});
