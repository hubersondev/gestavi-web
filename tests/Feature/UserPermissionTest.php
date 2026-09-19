<?php

use App\Enums\UserType;
use App\Models\PlatformRole;
use App\Models\User;

// ──────────────────────────────────────────────
//  Helpers plateforme
// ──────────────────────────────────────────────

it('identifie un super admin', function () {
    $user = User::factory()->superAdmin()->create();

    expect($user->isSuperAdmin())->toBeTrue();
    expect($user->isPlatformUser())->toBeTrue();
});

it('identifie un utilisateur plateforme', function () {
    $user = User::factory()->platformUser()->create();

    expect($user->isPlatformUser())->toBeTrue();
    expect($user->isSuperAdmin())->toBeFalse();
});

it('identifie un farmer', function () {
    $user = User::factory()->create(['type' => UserType::Farmer]);

    expect($user->isPlatformUser())->toBeFalse();
    expect($user->isSuperAdmin())->toBeFalse();
    expect($user->type)->toBe(UserType::Farmer);
});

// ──────────────────────────────────────────────
//  hasPermission
// ──────────────────────────────────────────────

it('super admin a toutes les permissions', function () {
    $user = User::factory()->superAdmin()->create();

    expect($user->hasPermission('users.view'))->toBeTrue();
    expect($user->hasPermission('roles.delete'))->toBeTrue();
    expect($user->hasPermission('settings.update'))->toBeTrue();
});

it('admin a les permissions de son rôle', function () {
    $user = User::factory()->admin()->create();

    expect($user->hasPermission('users.view'))->toBeTrue();
    expect($user->hasPermission('users.create'))->toBeTrue();
    expect($user->hasPermission('roles.view'))->toBeTrue();
});

it('admin n\'a pas les permissions de gestion des rôles', function () {
    $user = User::factory()->admin()->create();

    expect($user->hasPermission('roles.create'))->toBeFalse();
    expect($user->hasPermission('roles.update'))->toBeFalse();
    expect($user->hasPermission('roles.delete'))->toBeFalse();
});

it('utilisateur standard a uniquement les permissions de consultation', function () {
    $user = User::factory()->platformUser()->create();

    expect($user->hasPermission('users.view'))->toBeTrue();
    expect($user->hasPermission('organisations.view'))->toBeTrue();

    expect($user->hasPermission('users.create'))->toBeFalse();
    expect($user->hasPermission('users.update'))->toBeFalse();
    expect($user->hasPermission('users.delete'))->toBeFalse();
});

it('un farmer n\'a aucune permission', function () {
    $user = User::factory()->create(['type' => UserType::Farmer]);

    expect($user->hasPermission('users.view'))->toBeFalse();
});

// ──────────────────────────────────────────────
//  PlatformRole model
// ──────────────────────────────────────────────

it('le modèle PlatformRole vérifie ses permissions', function () {
    $role = PlatformRole::where('slug', 'admin')->first();

    expect($role->hasPermission('users.view'))->toBeTrue();
    expect($role->hasPermission('roles.create'))->toBeFalse();
    expect($role->isSuperAdmin())->toBeFalse();
});

it('le modèle PlatformRole identifie le super admin', function () {
    $role = PlatformRole::where('slug', 'super-admin')->first();

    expect($role->isSuperAdmin())->toBeTrue();
});
