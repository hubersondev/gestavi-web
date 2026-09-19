<?php

use App\Enums\UserType;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\OrganisationsRelationManager;
use App\Models\PlatformRole;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

// ──────────────────────────────────────────────
//  Liste
// ──────────────────────────────────────────────

it('peut afficher la liste des utilisateurs', function () {
    actingAs(User::factory()->superAdmin()->create());

    $users = User::factory()->platformUser()->count(3)->create();

    Livewire::test(ListUsers::class)
        ->assertOk()
        ->assertCanSeeTableRecords($users);
});

it('peut rechercher des utilisateurs par nom', function () {
    actingAs(User::factory()->superAdmin()->create());

    $target = User::factory()->platformUser()->create(['name' => 'Jean Dupont']);
    $other = User::factory()->platformUser()->create(['name' => 'Marie Martin']);

    Livewire::test(ListUsers::class)
        ->searchTable('Jean Dupont')
        ->assertCanSeeTableRecords([$target])
        ->assertCanNotSeeTableRecords([$other]);
});

it('peut filtrer par type', function () {
    actingAs(User::factory()->superAdmin()->create());

    $systemUser = User::factory()->platformUser()->create();
    // Note : les farmers ne sont pas visibles dans cette resource (scope platformUsers)

    Livewire::test(ListUsers::class)
        ->filterTable('type', UserType::System->value)
        ->assertCanSeeTableRecords([$systemUser]);
});

// ──────────────────────────────────────────────
//  Création
// ──────────────────────────────────────────────

it('peut créer un utilisateur', function () {
    actingAs(User::factory()->superAdmin()->create());

    $userRole = PlatformRole::where('slug', 'user')->first();

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Nouveau Utilisateur',
            'email' => 'nouveau@example.com',
            'phone' => '+225 0102030405',
            'password' => 'motdepasse123',
            'type' => UserType::System->value,
            'platform_role' => $userRole->id,
            'is_active' => true,
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseHas(User::class, [
        'name' => 'Nouveau Utilisateur',
        'email' => 'nouveau@example.com',
        'type' => UserType::System->value,
    ]);

    $user = User::where('email', 'nouveau@example.com')->first();
    expect($user->platformRoles->first()->id)->toBe($userRole->id);
});

it('valide les champs requis à la création', function (array $data, array $errors) {
    actingAs(User::factory()->superAdmin()->create());

    $userRole = PlatformRole::where('slug', 'user')->first();

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'motdepasse123',
            'type' => UserType::System->value,
            'platform_role' => $userRole->id,
            ...$data,
        ])
        ->call('create')
        ->assertHasFormErrors($errors);
})->with([
    'nom requis' => [['name' => null], ['name' => 'required']],
    'email requis' => [['email' => null], ['email' => 'required']],
    'email valide' => [['email' => 'pas-un-email'], ['email' => 'email']],
    'mot de passe requis' => [['password' => null], ['password' => 'required']],
    'mot de passe min 8' => [['password' => 'court'], ['password' => 'min']],
]);

// ──────────────────────────────────────────────
//  Édition
// ──────────────────────────────────────────────

it('peut modifier un utilisateur', function () {
    actingAs(User::factory()->superAdmin()->create());

    $user = User::factory()->platformUser()->create();

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->fillForm([
            'name' => 'Nom Modifié',
            'email' => $user->email,
            'type' => UserType::System->value,
            'platform_role' => $user->platformRoles->first()->id,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    assertDatabaseHas(User::class, [
        'id' => $user->id,
        'name' => 'Nom Modifié',
    ]);
});

it('ne requiert pas le mot de passe en édition', function () {
    actingAs(User::factory()->superAdmin()->create());

    $user = User::factory()->platformUser()->create();

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->fillForm([
            'name' => 'Modification Sans MDP',
            'email' => $user->email,
            'phone' => '+22501020304',
            'password' => '',
            'type' => UserType::System->value,
            'platform_role' => $user->platformRoles->first()->id,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    assertDatabaseHas(User::class, [
        'id' => $user->id,
        'name' => 'Modification Sans MDP',
    ]);
});

// ──────────────────────────────────────────────
//  Vue
// ──────────────────────────────────────────────

it('peut afficher les détails d\'un utilisateur', function () {
    actingAs(User::factory()->superAdmin()->create());

    $user = User::factory()->platformUser()->create();

    Livewire::test(ViewUser::class, ['record' => $user->id])
        ->assertOk()
        ->assertSchemaStateSet([
            'name' => $user->name,
            'email' => $user->email,
        ]);
});

it('affiche le relation manager organisations sur la page vue', function () {
    actingAs(User::factory()->superAdmin()->create());

    $user = User::factory()->platformUser()->create();

    Livewire::test(ViewUser::class, ['record' => $user->id])
        ->assertOk()
        ->assertSeeLivewire(OrganisationsRelationManager::class);
});

// ──────────────────────────────────────────────
//  Sécurité
// ──────────────────────────────────────────────

it('interdit l\'accès aux farmers', function () {
    $user = User::factory()->create(['type' => UserType::Farmer]);
    actingAs($user);

    Livewire::test(ListUsers::class)
        ->assertForbidden();
});

it('un admin peut créer un utilisateur standard', function () {
    actingAs(User::factory()->admin()->create());

    $userRole = PlatformRole::where('slug', 'user')->first();

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Utilisateur Standard',
            'email' => 'standard@example.com',
            'password' => 'motdepasse123',
            'type' => UserType::System->value,
            'platform_role' => $userRole->id,
            'is_active' => true,
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();
});

it('un admin ne peut pas modifier un super admin', function () {
    actingAs(User::factory()->admin()->create());

    $superAdmin = User::factory()->superAdmin()->create();

    Livewire::test(EditUser::class, ['record' => $superAdmin->id])
        ->assertForbidden();
});
