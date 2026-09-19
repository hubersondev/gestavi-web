<?php

use App\Enums\MemberStatus;
use App\Filament\Farm\Resources\Sales\SaleResource;
use App\Models\Organisation;
use App\Models\OrganisationPermission;
use App\Models\User;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);

    $this->organisation = Organisation::factory()->create();

    $this->readOnlyRole = $this->organisation->organisationRoles()->create([
        'name' => 'Lecteur Ventes',
        'slug' => 'sales-reader',
        'weight' => 1,
        'is_system' => false,
    ]);

    $salesViewId = OrganisationPermission::where('slug', 'sales.view')->value('id');
    $this->readOnlyRole->permissions()->attach($salesViewId);

    $this->user = User::factory()->create();
    $this->organisation->addMember($this->user, $this->readOnlyRole, MemberStatus::Active);

    actingAs($this->user);
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($this->organisation);

    cache()->driver('array')->flush();
});

it('un membre avec sales.view peut consulter la liste des factures', function () {
    expect(SaleResource::canViewAny())->toBeTrue();
});

it('un membre sans sales.create ne peut pas créer une facture', function () {
    expect(SaleResource::canCreate())->toBeFalse();
});

it('un membre sans sales.update ne peut pas éditer une facture', function () {
    $sale = \App\Models\Sale::factory()->create(['organisation_id' => $this->organisation->id]);

    expect(SaleResource::canEdit($sale))->toBeFalse();
});

it('un membre sans sales.delete ne peut pas supprimer une facture', function () {
    $sale = \App\Models\Sale::factory()->create(['organisation_id' => $this->organisation->id]);

    expect(SaleResource::canDelete($sale))->toBeFalse()
        ->and(SaleResource::canDeleteAny())->toBeFalse();
});

it('un owner avec toutes les permissions peut tout faire sur les factures', function () {
    $owner = User::factory()->create();
    $ownerRole = $this->organisation->organisationRoles()->where('slug', 'owner')->first();
    $this->organisation->addMember($owner, $ownerRole);

    actingAs($owner);
    cache()->driver('array')->flush();

    expect(SaleResource::canViewAny())->toBeTrue()
        ->and(SaleResource::canCreate())->toBeTrue()
        ->and(SaleResource::canDeleteAny())->toBeTrue();
});
