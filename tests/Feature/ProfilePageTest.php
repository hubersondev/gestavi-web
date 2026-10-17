<?php

use App\Filament\Auth\EditProfile;
use App\Models\Organisation;
use App\Models\User;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

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

it('exige le mot de passe actuel pour changer de mot de passe, et le vérifie', function () {
    $organisation = Organisation::factory()->create();
    $owner = User::factory()->create(['password' => 'AncienMotDePasse!1']);
    $organisation->addMember($owner, 'owner');

    actingAs($owner);
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($organisation);

    // Sans mot de passe actuel : refusé
    Livewire::test(EditProfile::class)
        ->fillForm(['password' => 'NouveauMotDePasse!2', 'passwordConfirmation' => 'NouveauMotDePasse!2'])
        ->call('save')
        ->assertHasFormErrors(['currentPassword' => 'required']);

    // Mauvais mot de passe actuel : refusé
    Livewire::test(EditProfile::class)
        ->fillForm(['currentPassword' => 'faux', 'password' => 'NouveauMotDePasse!2', 'passwordConfirmation' => 'NouveauMotDePasse!2'])
        ->call('save')
        ->assertHasFormErrors(['currentPassword']);

    expect(Hash::check('AncienMotDePasse!1', $owner->fresh()->password))->toBeTrue();

    // Bon mot de passe actuel : accepté
    Livewire::test(EditProfile::class)
        ->fillForm(['currentPassword' => 'AncienMotDePasse!1', 'password' => 'NouveauMotDePasse!2', 'passwordConfirmation' => 'NouveauMotDePasse!2'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('NouveauMotDePasse!2', $owner->fresh()->password))->toBeTrue();
});
