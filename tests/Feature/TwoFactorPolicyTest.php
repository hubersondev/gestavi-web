<?php

use App\Models\Organisation;
use App\Models\User;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;

/**
 * L'authentification à deux facteurs est exigée des utilisateurs plateforme
 * et des propriétaires/administrateurs d'organisation, pas des employés.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);
    $this->organisation = Organisation::factory()->create();
    $this->roles = $this->organisation->organisationRoles()->get()->keyBy('slug');
});

it('le modèle utilisateur supporte l\'application TOTP et les codes de récupération', function () {
    $user = User::factory()->create();

    expect($user)->toBeInstanceOf(HasAppAuthentication::class);

    $user->saveAppAuthenticationSecret('SECRET123');
    $user->saveAppAuthenticationRecoveryCodes(['aaa', 'bbb']);

    $fresh = $user->fresh();

    expect($fresh->getAppAuthenticationSecret())->toBe('SECRET123')
        ->and($fresh->getAppAuthenticationRecoveryCodes())->toBe(['aaa', 'bbb'])
        ->and($fresh->hasTwoFactorEnabled())->toBeTrue()
        ->and($fresh->getRawOriginal('app_authentication_secret'))->not->toBe('SECRET123');
});

it('exige le 2FA des propriétaires et administrateurs, pas des employés', function () {
    $owner = User::factory()->create();
    $employee = User::factory()->create();
    $this->organisation->addMember($owner, $this->roles['owner']);
    $this->organisation->addMember($employee, $this->roles['employee']);

    expect($owner->mustUseTwoFactor())->toBeTrue()
        ->and($employee->mustUseTwoFactor())->toBeFalse()
        ->and(User::factory()->admin()->create()->mustUseTwoFactor())->toBeTrue();
});

it('le panel ferme applique l\'exigence selon l\'utilisateur connecté', function () {
    $owner = User::factory()->create();
    $employee = User::factory()->create();
    $this->organisation->addMember($owner, $this->roles['owner']);
    $this->organisation->addMember($employee, $this->roles['employee']);

    $panel = Filament::getPanel('farm');
    Filament::setCurrentPanel($panel);

    actingAs($owner);
    expect($panel->isMultiFactorAuthenticationRequired())->toBeTrue();

    actingAs($employee);
    expect($panel->isMultiFactorAuthenticationRequired())->toBeFalse();
});
