<?php

use App\Filament\Resources\FarmUsers\FarmUserResource;
use App\Filament\Resources\FarmUsers\Pages\ViewFarmUser;
use App\Filament\Resources\Organisations\OrganisationResource;
use App\Filament\Resources\Organisations\Pages\ViewOrganisation;
use App\Filament\Resources\Organisations\RelationManagers\FarmsRelationManager;
use App\Filament\Resources\Organisations\RelationManagers\MembersRelationManager;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Filament\Widgets\PlatformHealthWidget;
use App\Filament\Widgets\PlatformStatsWidget;
use App\Filament\Widgets\SubscriptionDeadlinesWidget;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Farm;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\OrganisationAlertNotification;
use App\Services\Impersonation;
use App\Services\PlatformHealth;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Panel Admin : tableau de bord (indicateurs, échéances, état d'exploitation), fiche organisation
 * (fermes, membres), outils de support sur un utilisateur ferme (connexion en tant que, lien de mot
 * de passe, réinitialisation 2FA, déconnexion des appareils), avec leur trace dans le journal d'audit.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);
    Notification::fake();

    $this->admin = User::factory()->superAdmin()->create(['name' => 'Support GESTAVI']);
    $plan = Plan::factory()->create(['name' => 'Pro', 'monthly_price' => 25000, 'max_farms' => 5, 'max_users' => 15]);
    $this->organisation = Organisation::factory()->create(['plan_id' => $plan->id, 'name' => 'Ferme Test']);
    $this->owner = User::factory()->create(['name' => 'Awa Propriétaire', 'email' => 'awa@test.ci']);
    $this->organisation->addMember($this->owner, 'owner');
    $this->farm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);
    Subscription::factory()->endingIn(3)->create(['organisation_id' => $this->organisation->id, 'plan_id' => $plan->id]);

    actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('affiche les indicateurs, les échéances et l\'état d\'exploitation sur le tableau de bord', function () {
    Organisation::factory()->readOnly()->create();

    Livewire::test(PlatformStatsWidget::class)
        ->assertOk()
        ->assertSee('Organisations actives')
        ->assertSee('1 en lecture seule')
        ->assertSee('1 à échéance sous 7 jours');

    Livewire::test(SubscriptionDeadlinesWidget::class)
        ->assertOk()
        ->assertCanSeeTableRecords(Subscription::query()->get());

    Livewire::test(PlatformHealthWidget::class)->assertOk()->assertSee('Planificateur')->assertSee('Sauvegardes');

    $checks = collect(app(PlatformHealth::class)->checks())->keyBy('check');
    expect($checks)->toHaveKeys(['Planificateur', 'File d\'attente', 'Tâches en échec', 'Sauvegardes', 'Courriel', 'Double authentification'])
        ->and($checks['Double authentification']['status'])->toBe(PlatformHealth::STATUS_WARN); // comptes de test sans 2FA
});

it('la fiche organisation montre ses fermes et ses membres, et son usage face au plan', function () {
    Livewire::test(FarmsRelationManager::class, ['ownerRecord' => $this->organisation, 'pageClass' => ViewOrganisation::class])
        ->assertOk()
        ->assertCanSeeTableRecords([$this->farm]);

    Livewire::test(MembersRelationManager::class, ['ownerRecord' => $this->organisation, 'pageClass' => ViewOrganisation::class])
        ->assertOk()
        ->assertCanSeeTableRecords($this->organisation->memberships()->get())
        ->assertSee('Awa Propriétaire');

    Livewire::test(ViewOrganisation::class, ['record' => $this->organisation->getKey()])
        ->assertOk()
        ->assertSee('1 ferme(s) sur 5');
});

it('permet au support de se connecter en tant qu\'un utilisateur ferme, trace la session et revient', function () {
    expect(Impersonation::canImpersonate($this->admin, $this->owner))->toBeTrue()
        ->and(Impersonation::canImpersonate($this->admin, $this->admin))->toBeFalse()
        ->and(Impersonation::canImpersonate($this->owner, $this->admin))->toBeFalse();

    Livewire::test(ViewFarmUser::class, ['record' => $this->owner->getKey()])
        ->callAction('impersonate')
        ->assertHasNoActionErrors();

    expect(auth()->id())->toBe($this->owner->id)
        ->and(Impersonation::isActive())->toBeTrue()
        ->and(Impersonation::impersonator()?->id)->toBe($this->admin->id);

    // Une écriture faite pendant la session porte l'identifiant de l'administrateur
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($this->organisation);
    $account = Account::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'name' => 'Caisse ouverte par le support']);
    $entry = AuditLog::query()->where('subject_type', Account::class)->where('subject_id', $account->id)->latest('id')->first();
    expect($entry->impersonator()?->id)->toBe($this->admin->id)
        ->and($entry->causerLabel())->toContain('Awa Propriétaire', 'Support GESTAVI');

    get(route('impersonation.leave'))->assertRedirect();

    expect(auth()->id())->toBe($this->admin->id)
        ->and(Impersonation::isActive())->toBeFalse()
        ->and(AuditLog::query()->where('subject_type', User::class)->where('subject_id', $this->owner->id)->pluck('event')->all())
        ->toBe(['impersonation_started', 'impersonation_ended']);
});

it('envoie un lien de mot de passe, réinitialise la 2FA et déconnecte les appareils, avec trace', function () {
    $this->owner->saveAppAuthenticationSecret('SECRET-2FA');
    $this->owner->saveAppAuthenticationRecoveryCodes(['a', 'b']);
    $this->owner->createToken('Téléphone 1');
    $this->owner->createToken('Téléphone 2');

    Livewire::test(ViewFarmUser::class, ['record' => $this->owner->getKey()])
        ->callAction('sendResetLink')->assertHasNoActionErrors()
        ->callAction('resetTwoFactor')->assertHasNoActionErrors()
        ->callAction('revokeTokens')->assertHasNoActionErrors();

    Notification::assertSentTo($this->owner, ResetPassword::class);

    $owner = $this->owner->fresh();
    expect($owner->hasTwoFactorEnabled())->toBeFalse()
        ->and($owner->getAppAuthenticationRecoveryCodes())->toBeNull()
        ->and($owner->tokens()->count())->toBe(0)
        ->and(AuditLog::query()->where('subject_type', User::class)->where('subject_id', $owner->id)->pluck('event')->sort()->values()->all())
        ->toBe(['password_reset_link_sent', 'tokens_revoked', 'two_factor_reset']);

    Notification::assertNotSentTo($this->owner, OrganisationAlertNotification::class);
});

it('propose les structures, utilisateurs et abonnements à la recherche globale', function () {
    expect(OrganisationResource::getGloballySearchableAttributes())->toContain('name', 'email')
        ->and(FarmUserResource::getGloballySearchableAttributes())->toContain('email')
        ->and(SubscriptionResource::getGloballySearchableAttributes())->toContain('organisation.name');
});
