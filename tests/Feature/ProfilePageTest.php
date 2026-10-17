<?php

use App\Models\Organisation;
use App\Models\User;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Page de profil des deux panels : l'utilisateur gère lui-même mot de passe, courriel et double
 * authentification (configuration volontaire, codes de secours), sans passer par le support.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);
});

it('offre la page de profil et la double authentification dans le panel Ferme, même aux rôles non astreints', function () {
    $organisation = Organisation::factory()->create();
    $employee = User::factory()->create();
    $organisation->addMember($employee, 'employee');

    actingAs($employee);
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($organisation);

    expect($employee->mustUseTwoFactor())->toBeFalse();

    get(Filament::getPanel('farm')->getProfileUrl(['tenant' => $organisation]))
        ->assertOk()
        ->assertSee('authenti', escape: false);
});

it('offre la page de profil dans le panel Admin', function () {
    $admin = User::factory()->superAdmin()->create();
    $admin->saveAppAuthenticationSecret('SECRET');
    $admin->saveAppAuthenticationRecoveryCodes(['a', 'b']);

    actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    get(Filament::getPanel('admin')->getProfileUrl())->assertOk();
});
