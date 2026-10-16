<?php

use App\Enums\BillingCycle;
use App\Enums\PlanFeature;
use App\Enums\SubscriptionStatus;
use App\Exceptions\SubscriptionException;
use App\Filament\Resources\Subscriptions\Pages\CreateSubscription;
use App\Models\Farm;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\OrganisationAlertNotification;
use App\Services\SubscriptionService;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * Abonnements : souscription (essai / payée) qui synchronise le plan de l'organisation,
 * renouvellement, changement de plan sous contrainte des limites, annulation, expiration
 * quotidienne avec retour au plan gratuit, alertes J-7 / J-3 / J-1 dédupliquées, création admin.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);
    Notification::fake();

    $this->free = Plan::factory()->create(['name' => 'Starter', 'monthly_price' => 0, 'yearly_price' => 0, 'max_farms' => 1, 'max_users' => 3, 'sort_order' => 1, 'features' => [PlanFeature::Flocks->value]]);
    $this->pro = Plan::factory()->create(['name' => 'Pro', 'monthly_price' => 25000, 'yearly_price' => 250000, 'max_farms' => 5, 'max_users' => 15, 'trial_days' => 14, 'sort_order' => 2, 'features' => [PlanFeature::Flocks->value, PlanFeature::AiPredictions->value]]);
    $this->enterprise = Plan::factory()->create(['name' => 'Entreprise', 'monthly_price' => 75000, 'yearly_price' => 750000, 'max_farms' => null, 'max_users' => null, 'sort_order' => 3, 'features' => collect(PlanFeature::cases())->map->value->all()]);

    $this->organisation = Organisation::factory()->create(['plan_id' => $this->free->id]);
    $this->owner = User::factory()->create();
    $this->organisation->addMember($this->owner, 'owner');
    Farm::factory()->create(['organisation_id' => $this->organisation->id]);

    $this->service = app(SubscriptionService::class);
});

it('souscrit un essai puis un abonnement payé, en synchronisant le plan de l\'organisation', function () {
    $trial = $this->service->start($this->organisation, $this->pro, trialDays: 14);

    expect($trial->status)->toBe(SubscriptionStatus::Trial)
        ->and((float) $trial->price)->toBe(0.0)
        ->and($trial->ends_at->toDateString())->toBe(today()->addDays(14)->toDateString())
        ->and($trial->trial_ends_at->toDateString())->toBe(today()->addDays(14)->toDateString())
        ->and($this->organisation->fresh()->plan_id)->toBe($this->pro->id)
        ->and($this->organisation->fresh()->hasFeature(PlanFeature::AiPredictions))->toBeTrue()
        ->and($this->organisation->currentSubscription()?->id)->toBe($trial->id);

    // Souscription payée annuelle : l'essai est clos et remplacé
    $paid = $this->service->start($this->organisation, $this->enterprise, BillingCycle::Yearly, payment: ['payment_method' => 'wave', 'payment_reference' => 'WV-1'], by: $this->owner);

    expect($paid->status)->toBe(SubscriptionStatus::Active)
        ->and((float) $paid->price)->toBe(750000.0)
        ->and($paid->ends_at->toDateString())->toBe(today()->addMonths(12)->subDay()->toDateString())
        ->and($paid->payment_method?->value)->toBe('wave')
        ->and($paid->created_by)->toBe($this->owner->id)
        ->and($trial->fresh()->status)->toBe(SubscriptionStatus::Expired) // remplacé : passe à l'historique
        ->and($trial->fresh()->cancellation_reason)->toContain('Remplacé')
        ->and($trial->fresh()->isCurrent())->toBeFalse()
        ->and($this->organisation->fresh()->plan_id)->toBe($this->enterprise->id)
        ->and($this->organisation->currentSubscription()?->id)->toBe($paid->id);
});

it('renouvelle à partir de l\'échéance à venir, ou d\'aujourd\'hui si elle est passée', function () {
    $subscription = Subscription::factory()->endingIn(10)->create(['organisation_id' => $this->organisation->id, 'plan_id' => $this->pro->id]);

    $this->service->renew($subscription, BillingCycle::Quarterly, ['payment_reference' => 'OM-42', 'payment_method' => 'orange_money']);

    expect($subscription->ends_at->toDateString())->toBe(today()->addDays(10)->addMonths(3)->subDay()->toDateString())
        ->and((float) $subscription->price)->toBe(75000.0)
        ->and($subscription->billing_cycle)->toBe(BillingCycle::Quarterly)
        ->and($subscription->payment_reference)->toBe('OM-42')
        ->and($this->organisation->fresh()->plan_id)->toBe($this->pro->id);

    $expired = Subscription::factory()->expired()->create(['organisation_id' => $this->organisation->id, 'plan_id' => $this->pro->id]);
    $this->service->renew($expired);

    expect($expired->status)->toBe(SubscriptionStatus::Active)
        ->and($expired->starts_at->toDateString())->toBe(today()->toDateString())
        ->and($expired->ends_at->toDateString())->toBe(today()->addMonth()->subDay()->toDateString());
});

it('refuse un plan dont les limites sont dépassées et change de plan sinon', function () {
    Farm::factory()->count(2)->create(['organisation_id' => $this->organisation->id]); // 3 fermes
    $subscription = $this->service->start($this->organisation, $this->pro);

    expect(fn () => $this->service->changePlan($subscription, $this->free))
        ->toThrow(SubscriptionException::class, 'autorise 1 ferme(s) au maximum');

    $this->service->changePlan($subscription, $this->enterprise);

    expect($subscription->plan_id)->toBe($this->enterprise->id)
        ->and($subscription->plan_name)->toBe('Entreprise')
        ->and((float) $subscription->price)->toBe(75000.0)
        ->and($this->organisation->fresh()->plan_id)->toBe($this->enterprise->id);

    // Souscrire directement un plan trop petit est refusé aussi
    expect(fn () => $this->service->start($this->organisation, $this->free))->toThrow(SubscriptionException::class);
});

it('annule en maintenant l\'accès jusqu\'à l\'échéance, ou immédiatement avec retour au plan gratuit', function () {
    $subscription = $this->service->start($this->organisation, $this->pro);

    $this->service->cancel($subscription, 'Trop cher');

    expect($subscription->status)->toBe(SubscriptionStatus::Cancelled)
        ->and($subscription->cancellation_reason)->toBe('Trop cher')
        ->and($subscription->isCurrent())->toBeTrue()
        ->and($this->organisation->fresh()->plan_id)->toBe($this->pro->id);

    expect(fn () => $this->service->cancel($subscription))->toThrow(SubscriptionException::class);

    $second = $this->service->start($this->organisation, $this->pro);
    $this->service->cancel($second, immediately: true);

    expect($second->fresh()->status)->toBe(SubscriptionStatus::Expired)
        ->and($this->organisation->fresh()->plan_id)->toBe($this->free->id);
    Notification::assertSentTo($this->owner, OrganisationAlertNotification::class, fn (OrganisationAlertNotification $n): bool => $n->alertKey === 'subscription_expired');
});

it('expire les abonnements échus et alerte J-7, J-3, J-1 une seule fois par seuil', function () {
    $overdue = Subscription::factory()->create(['organisation_id' => $this->organisation->id, 'plan_id' => $this->pro->id, 'starts_at' => today()->subMonths(2)->toDateString(), 'ends_at' => today()->subDay()->toDateString()]);
    $this->organisation->forceFill(['plan_id' => $this->pro->id])->save();

    $other = Organisation::factory()->create(['plan_id' => $this->pro->id]);
    $otherOwner = User::factory()->create();
    $other->addMember($otherOwner, 'owner');
    $soon = Subscription::factory()->endingIn(5)->create(['organisation_id' => $other->id, 'plan_id' => $this->pro->id]);
    $far = Subscription::factory()->endingIn(20)->create(['organisation_id' => Organisation::factory()->create()->id, 'plan_id' => $this->pro->id]);

    $this->artisan('gestavi:subscriptions')->assertSuccessful();

    expect($overdue->fresh()->status)->toBe(SubscriptionStatus::Expired)
        ->and($this->organisation->fresh()->plan_id)->toBe($this->free->id)
        ->and($soon->fresh()->alerted_thresholds)->toBe([7])
        ->and($far->fresh()->alerted_thresholds)->toBeNull();

    Notification::assertSentTo($this->owner, OrganisationAlertNotification::class, fn (OrganisationAlertNotification $n): bool => $n->alertKey === 'subscription_expired' && $n->status === 'danger');
    Notification::assertSentTo($otherOwner, OrganisationAlertNotification::class, fn (OrganisationAlertNotification $n): bool => $n->alertKey === 'subscription_expiry' && str_contains($n->title, 'dans 5 jours'));

    // Rejeu le même jour : rien de nouveau
    Notification::fake();
    $this->artisan('gestavi:subscriptions')->assertSuccessful();
    Notification::assertNothingSent();

    // À J-1 : nouveau seuil, alerte critique
    $soon->forceFill(['ends_at' => today()->addDay()->toDateString()])->save();
    $this->artisan('gestavi:subscriptions')->assertSuccessful();
    expect($soon->fresh()->alerted_thresholds)->toBe([1, 7]);
    Notification::assertSentTo($otherOwner, OrganisationAlertNotification::class, fn (OrganisationAlertNotification $n): bool => $n->alertKey === 'subscription_expiry' && $n->status === 'danger' && str_contains($n->title, 'demain'));
});

it('crée un abonnement depuis le panel admin via le service', function () {
    $admin = User::factory()->superAdmin()->create();
    actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(CreateSubscription::class)
        ->fillForm([
            'organisation_id' => $this->organisation->id,
            'plan_id' => $this->pro->id,
            'billing_cycle' => BillingCycle::Yearly->value,
            'trial_days' => 0,
            'starts_at' => today()->toDateString(),
            'price' => 250000,
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'VIR-7',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $subscription = $this->organisation->currentSubscription();

    expect($subscription)->not->toBeNull()
        ->and($subscription->plan_name)->toBe('Pro')
        ->and($subscription->billing_cycle)->toBe(BillingCycle::Yearly)
        ->and((float) $subscription->price)->toBe(250000.0)
        ->and($subscription->ends_at->toDateString())->toBe(today()->addYear()->subDay()->toDateString())
        ->and($subscription->created_by)->toBe($admin->id)
        ->and($this->organisation->fresh()->plan_id)->toBe($this->pro->id);
});
