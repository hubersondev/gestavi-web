<?php

use App\Enums\AnnouncementLevel;
use App\Enums\BillingCycle;
use App\Enums\OrganisationStatus;
use App\Enums\UserType;
use App\Filament\Pages\PlatformSettingsPage;
use App\Filament\Pages\SubscriptionRevenue;
use App\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Filament\Resources\Organisations\Pages\CreateOrganisation;
use App\Filament\Resources\Subscriptions\Pages\ViewSubscription;
use App\Filament\Resources\Subscriptions\RelationManagers\PaymentsRelationManager;
use App\Models\Announcement;
use App\Models\City;
use App\Models\Country;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Notifications\OrganisationAlertNotification;
use App\Services\PlatformSettings;
use App\Services\SubscriptionService;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Panel Admin (suite) : réglages de la plateforme, encaissements et factures d'abonnement,
 * assistant de création d'organisation, annonces aux organisations.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);
    Notification::fake();
    PlatformSettings::flushCache();

    $this->admin = User::factory()->superAdmin()->create();
    $this->pro = Plan::factory()->create(['name' => 'Pro', 'monthly_price' => 25000, 'yearly_price' => 250000, 'trial_days' => 14, 'max_farms' => 5, 'max_users' => 15, 'sort_order' => 2]);
    $this->organisation = Organisation::factory()->create(['plan_id' => $this->pro->id, 'name' => 'Ferme Bêta']);
    $this->owner = User::factory()->create();
    $this->organisation->addMember($this->owner, 'owner');

    actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('enregistre les réglages de la plateforme et les applique (support, délai de grâce)', function () {
    Livewire::test(PlatformSettingsPage::class)
        ->assertOk()
        ->fillForm([
            'company_name' => 'Digitech Group CI',
            'company_tax_id' => 'CI-ABJ-2026-X-12345',
            'support_email' => 'aide@gestavi.ci',
            'subscription_grace_days' => 10,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    PlatformSettings::flushCache();

    expect(PlatformSettings::companyName())->toBe('Digitech Group CI')
        ->and(PlatformSettings::supportEmail())->toBe('aide@gestavi.ci')
        ->and(SubscriptionService::graceDays())->toBe(10);

    // Le bandeau de lecture seule affiche le nouveau contact
    $this->organisation->setStatus(OrganisationStatus::ReadOnly, 'Test');
    actingAs($this->owner);
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($this->organisation);
    get(Filament::getUrl($this->organisation))->assertOk()->assertSee('aide@gestavi.ci');
});

it('numérote et facture chaque encaissement : souscription payée, renouvellement, pas d\'essai', function () {
    $service = app(SubscriptionService::class);

    $trial = $service->start($this->organisation, $this->pro, trialDays: 14);
    expect(SubscriptionPayment::query()->count())->toBe(0);

    $paid = $service->start($this->organisation, $this->pro, BillingCycle::Yearly, payment: ['payment_method' => 'wave', 'payment_reference' => 'WV-99'], by: $this->admin);
    $service->renew($paid, BillingCycle::Monthly, ['payment_method' => 'cash']);

    $payments = SubscriptionPayment::query()->orderBy('id')->get();
    $year = today()->year;

    expect($payments)->toHaveCount(2)
        ->and($payments[0]->invoice_number)->toBe("ABO-{$year}-00001")
        ->and($payments[1]->invoice_number)->toBe("ABO-{$year}-00002")
        ->and((float) $payments[0]->amount)->toBe(250000.0)
        ->and($payments[0]->payment_method?->value)->toBe('wave')
        ->and($payments[0]->period_from->toDateString())->toBe(today()->toDateString())
        ->and((float) $payments[1]->amount)->toBe(25000.0)
        ->and($payments[1]->period_from->toDateString())->toBe($paid->fresh()->ends_at->subMonth()->addDay()->toDateString())
        ->and($paid->payments()->count())->toBe(2);

    // La facture PDF se télécharge depuis la fiche et la page des encaissements montre les totaux
    Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $paid, 'pageClass' => ViewSubscription::class])
        ->assertOk()
        ->assertCanSeeTableRecords($payments);

    $response = PaymentsRelationManager::download($payments[0]);
    expect($response->headers->get('content-type'))->toBe('application/pdf');

    $revenue = Livewire::test(SubscriptionRevenue::class)->assertOk()->assertSee("ABO-{$year}-00001")->instance();
    expect($revenue->getTotals()['total'])->toBe(275000.0)
        ->and($revenue->getTotals()['by_plan'])->toBe(['Pro' => 275000.0])
        ->and($revenue->exportExcel()->headers->get('content-type'))->toContain('spreadsheetml');
});

it('crée une organisation, son propriétaire et son abonnement en une passe', function () {
    $country = Country::query()->first() ?? Country::factory()->create();
    $city = City::query()->where('country_id', $country->id)->first() ?? City::factory()->create(['country_id' => $country->id]);

    Livewire::test(CreateOrganisation::class)
        ->fillForm([
            'name' => 'Ferme du Nord',
            'email' => 'contact@nord.ci',
            'phone' => '+225 0100000000',
            'country_id' => $country->id,
            'city_id' => $city->id,
            'owner_name' => 'Moussa Nord',
            'owner_email' => 'moussa@nord.ci',
            'owner_send_link' => true,
            'plan_id' => $this->pro->id,
            'billing_cycle' => BillingCycle::Yearly->value,
            'trial_days' => 0,
            'price' => 250000,
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'VIR-NORD',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $organisation = Organisation::query()->where('name', 'Ferme du Nord')->firstOrFail();
    $owner = User::query()->where('email', 'moussa@nord.ci')->firstOrFail();

    expect($organisation->slug)->toBe('ferme-du-nord')
        ->and($owner->type)->toBe(UserType::Farmer)
        ->and($owner->membershipIn($organisation)?->isOwner())->toBeTrue()
        ->and($organisation->plan_id)->toBe($this->pro->id)
        ->and($organisation->currentSubscription()?->billing_cycle)->toBe(BillingCycle::Yearly)
        ->and(SubscriptionPayment::query()->where('organisation_id', $organisation->id)->count())->toBe(1);

    Notification::assertSentTo($owner, ResetPassword::class);
});

it('affiche les annonces visées dans l\'espace des organisations et les notifie aux responsables', function () {
    $other = Organisation::factory()->create(['plan_id' => Plan::factory()->create(['name' => 'Starter'])->id]);
    $otherOwner = User::factory()->create();
    $other->addMember($otherOwner, 'owner');

    $all = Announcement::factory()->create(['title' => 'Maintenance dimanche', 'created_by' => $this->admin->id]);
    $proOnly = Announcement::factory()->create(['title' => 'Nouveauté Pro : prévisions', 'plan_id' => $this->pro->id, 'level' => AnnouncementLevel::Warning]);
    Announcement::factory()->create(['title' => 'Annonce passée', 'ends_at' => now()->subDay()]);
    Announcement::factory()->create(['title' => 'Annonce désactivée', 'is_active' => false]);

    expect(Announcement::query()->current()->forOrganisation($this->organisation)->pluck('title')->sort()->values()->all())->toBe(['Maintenance dimanche', 'Nouveauté Pro : prévisions'])
        ->and(Announcement::query()->current()->forOrganisation($other)->pluck('title')->all())->toBe(['Maintenance dimanche']);

    Livewire::test(ListAnnouncements::class)
        ->assertOk()
        ->callTableAction('notify', $proOnly)
        ->assertHasNoTableActionErrors();

    Notification::assertSentTo($this->owner, OrganisationAlertNotification::class, fn (OrganisationAlertNotification $n): bool => $n->alertKey === 'announcement' && $n->title === 'Nouveauté Pro : prévisions' && $n->status === 'warning');
    Notification::assertNotSentTo($otherOwner, OrganisationAlertNotification::class);
    expect($proOnly->fresh()->notified_at)->not->toBeNull();

    // Le bandeau apparaît dans l'espace ferme
    actingAs($this->owner);
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($this->organisation);
    get(Filament::getUrl($this->organisation))->assertOk()->assertSee('Maintenance dimanche')->assertSee('Nouveauté Pro')->assertDontSee('Annonce passée');
});
