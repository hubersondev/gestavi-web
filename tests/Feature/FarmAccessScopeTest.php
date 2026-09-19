<?php

use App\Enums\MemberStatus;
use App\Models\Farm;
use App\Models\Organisation;
use App\Models\OrganisationRole;
use App\Models\OrganisationUser;
use App\Models\User;
use Database\Seeders\OrganisationPermissionSeeder;

beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);

    $this->organisation = Organisation::factory()->create();

    $this->ownerRole = $this->organisation->organisationRoles()->where('slug', 'owner')->first();
    $this->adminRole = $this->organisation->organisationRoles()->where('slug', 'admin')->first();
    $this->managerRole = $this->organisation->organisationRoles()->where('slug', 'manager')->first();
    $this->employeeRole = $this->organisation->organisationRoles()->where('slug', 'employee')->first();

    // Créer 3 fermes
    $this->farm1 = Farm::factory()->create(['organisation_id' => $this->organisation->id]);
    $this->farm2 = Farm::factory()->create(['organisation_id' => $this->organisation->id]);
    $this->farm3 = Farm::factory()->create(['organisation_id' => $this->organisation->id]);
});

// ──────────────────────────────────────────────
//  canAccessAllFarms()
// ──────────────────────────────────────────────

it('retourne true pour un owner', function () {
    $membership = createMembership($this->organisation, $this->ownerRole);

    expect($membership->canAccessAllFarms())->toBeTrue();
});

it('retourne true pour un admin', function () {
    $membership = createMembership($this->organisation, $this->adminRole);

    expect($membership->canAccessAllFarms())->toBeTrue();
});

it('retourne false pour un manager', function () {
    $membership = createMembership($this->organisation, $this->managerRole);

    expect($membership->canAccessAllFarms())->toBeFalse();
});

it('retourne false pour un employee', function () {
    $membership = createMembership($this->organisation, $this->employeeRole);

    expect($membership->canAccessAllFarms())->toBeFalse();
});

// ──────────────────────────────────────────────
//  Scope accessibleBy
// ──────────────────────────────────────────────

it('un owner voit toutes les fermes', function () {
    $membership = createMembership($this->organisation, $this->ownerRole);

    $farms = Farm::query()
        ->where('organisation_id', $this->organisation->id)
        ->accessibleBy($membership)
        ->get();

    expect($farms)->toHaveCount(3);
});

it('un admin voit toutes les fermes', function () {
    $membership = createMembership($this->organisation, $this->adminRole);

    $farms = Farm::query()
        ->where('organisation_id', $this->organisation->id)
        ->accessibleBy($membership)
        ->get();

    expect($farms)->toHaveCount(3);
});

it('un manager ne voit que les fermes assignées', function () {
    $membership = createMembership($this->organisation, $this->managerRole);
    $membership->farms()->attach([$this->farm1->id, $this->farm3->id]);

    $farms = Farm::query()
        ->where('organisation_id', $this->organisation->id)
        ->accessibleBy($membership)
        ->get();

    expect($farms)->toHaveCount(2)
        ->and($farms->pluck('id')->toArray())->toContain($this->farm1->id, $this->farm3->id);
});

it('un employee sans assignation ne voit aucune ferme', function () {
    $membership = createMembership($this->organisation, $this->employeeRole);

    $farms = Farm::query()
        ->where('organisation_id', $this->organisation->id)
        ->accessibleBy($membership)
        ->get();

    expect($farms)->toHaveCount(0);
});

it('un employee avec une ferme assignée ne voit que celle-ci', function () {
    $membership = createMembership($this->organisation, $this->employeeRole);
    $membership->farms()->attach($this->farm2->id);

    $farms = Farm::query()
        ->where('organisation_id', $this->organisation->id)
        ->accessibleBy($membership)
        ->get();

    expect($farms)->toHaveCount(1)
        ->and($farms->first()->id)->toBe($this->farm2->id);
});

// ──────────────────────────────────────────────
//  Relations
// ──────────────────────────────────────────────

it('la relation farms() sur OrganisationUser fonctionne', function () {
    $membership = createMembership($this->organisation, $this->employeeRole);
    $membership->farms()->attach([$this->farm1->id, $this->farm2->id]);

    expect($membership->farms)->toHaveCount(2);
});

it('la relation assignedMembers() sur Farm fonctionne', function () {
    $m1 = createMembership($this->organisation, $this->employeeRole);
    $m2 = createMembership($this->organisation, $this->managerRole);
    $this->farm1->assignedMembers()->attach([$m1->id, $m2->id]);

    expect($this->farm1->assignedMembers)->toHaveCount(2);
});

// ──────────────────────────────────────────────
//  Cascade delete
// ──────────────────────────────────────────────

it('la suppression d\'un membership nettoie les affectations de ferme', function () {
    $user = User::factory()->create();
    $membership = OrganisationUser::create([
        'organisation_id' => $this->organisation->id,
        'user_id' => $user->id,
        'organisation_role_id' => $this->employeeRole->id,
        'status' => MemberStatus::Active,
        'joined_at' => now(),
    ]);
    $membership->farms()->attach($this->farm1->id);

    expect(DB::table('farm_organisation_user')->where('organisation_user_id', $membership->id)->count())->toBe(1);

    // Supprimer le membership
    $membership->delete();

    expect(DB::table('farm_organisation_user')->where('organisation_user_id', $membership->id)->count())->toBe(0);
});

it('la suppression d\'une ferme nettoie les affectations', function () {
    $membership = createMembership($this->organisation, $this->employeeRole);
    $membership->farms()->attach($this->farm1->id);

    expect(DB::table('farm_organisation_user')->where('farm_id', $this->farm1->id)->count())->toBe(1);

    $this->farm1->delete();

    expect(DB::table('farm_organisation_user')->where('farm_id', $this->farm1->id)->count())->toBe(0);
});

// ──────────────────────────────────────────────
//  Helpers
// ──────────────────────────────────────────────

function createMembership(Organisation $organisation, OrganisationRole $role): OrganisationUser
{
    $user = User::factory()->create();

    return OrganisationUser::create([
        'organisation_id' => $organisation->id,
        'user_id' => $user->id,
        'organisation_role_id' => $role->id,
        'status' => MemberStatus::Active,
        'joined_at' => now(),
    ]);
}
