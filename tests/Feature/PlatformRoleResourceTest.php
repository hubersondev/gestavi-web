<?php

use App\Filament\Resources\PlatformRoles\Pages\CreatePlatformRole;
use App\Filament\Resources\PlatformRoles\Pages\EditPlatformRole;
use App\Filament\Resources\PlatformRoles\Pages\ListPlatformRoles;
use App\Filament\Resources\PlatformRoles\Pages\ViewPlatformRole;
use App\Models\PlatformPermission;
use App\Models\PlatformRole;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

// ──────────────────────────────────────────────
//  Liste
// ──────────────────────────────────────────────

it('peut afficher la liste des rôles', function () {
    actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ListPlatformRoles::class)
        ->assertOk();
});

it('peut rechercher un rôle par nom', function () {
    actingAs(User::factory()->superAdmin()->create());

    $role = PlatformRole::factory()->create(['name' => 'Gestionnaire']);

    Livewire::test(ListPlatformRoles::class)
        ->searchTable('Gestionnaire')
        ->assertCanSeeTableRecords([$role]);
});

// ──────────────────────────────────────────────
//  Création
// ──────────────────────────────────────────────

it('peut créer un rôle avec des permissions', function () {
    actingAs(User::factory()->superAdmin()->create());

    $permissions = PlatformPermission::where('group', 'users')->pluck('id')->toArray();

    Livewire::test(CreatePlatformRole::class)
        ->fillForm([
            'name' => 'Nouveau Rôle',
            'slug' => 'nouveau-role',
            'description' => 'Un rôle de test',
            'permissions' => $permissions,
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseHas(PlatformRole::class, [
        'name' => 'Nouveau Rôle',
        'slug' => 'nouveau-role',
    ]);

    $role = PlatformRole::where('slug', 'nouveau-role')->first();
    expect($role->permissions)->toHaveCount(count($permissions));
});

it('valide les champs requis à la création d\'un rôle', function (array $data, array $errors) {
    actingAs(User::factory()->superAdmin()->create());

    Livewire::test(CreatePlatformRole::class)
        ->fillForm([
            'name' => 'Test Rôle',
            'slug' => 'test-role',
            ...$data,
        ])
        ->call('create')
        ->assertHasFormErrors($errors);
})->with([
    'nom requis' => [['name' => null], ['name' => 'required']],
    'slug requis' => [['slug' => null], ['slug' => 'required']],
]);

// ──────────────────────────────────────────────
//  Édition
// ──────────────────────────────────────────────

it('peut modifier un rôle', function () {
    actingAs(User::factory()->superAdmin()->create());

    $role = PlatformRole::factory()->create();

    Livewire::test(EditPlatformRole::class, ['record' => $role->id])
        ->fillForm([
            'name' => 'Rôle Modifié',
            'slug' => $role->slug,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    assertDatabaseHas(PlatformRole::class, [
        'id' => $role->id,
        'name' => 'Rôle Modifié',
    ]);
});

// ──────────────────────────────────────────────
//  Vue
// ──────────────────────────────────────────────

it('peut afficher les détails d\'un rôle', function () {
    actingAs(User::factory()->superAdmin()->create());

    $role = PlatformRole::where('slug', 'admin')->first();

    Livewire::test(ViewPlatformRole::class, ['record' => $role->id])
        ->assertOk()
        ->assertSchemaStateSet([
            'name' => $role->name,
            'slug' => $role->slug,
        ]);
});

// ──────────────────────────────────────────────
//  Sécurité - Protection des rôles système
// ──────────────────────────────────────────────

it('un admin ne peut pas supprimer un rôle système', function () {
    $admin = User::factory()->admin()->create();
    actingAs($admin);

    $systemRole = PlatformRole::where('slug', 'user')->first();

    // Le policy interdit la suppression d'un rôle système
    expect($admin->can('delete', $systemRole))->toBeFalse();
});

it('un utilisateur standard ne peut pas créer de rôle', function () {
    actingAs(User::factory()->platformUser()->create());

    Livewire::test(CreatePlatformRole::class)
        ->assertForbidden();
});

it('un admin ne peut pas créer de rôle', function () {
    actingAs(User::factory()->admin()->create());

    Livewire::test(CreatePlatformRole::class)
        ->assertForbidden();
});
