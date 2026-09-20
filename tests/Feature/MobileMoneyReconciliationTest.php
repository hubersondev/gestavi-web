<?php

use App\Enums\AccountType;
use App\Enums\MobileMoneyOperator;
use App\Enums\PaymentMethod;
use App\Enums\PlanFeature;
use App\Enums\SaleStatus;
use App\Filament\Farm\Resources\MobileMoneyTransactions\MobileMoneyTransactionResource;
use App\Filament\Farm\Resources\MobileMoneyTransactions\Pages\ListMobileMoneyTransactions;
use App\Models\Account;
use App\Models\Customer;
use App\Models\MobileMoneyTransaction;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\Sale;
use App\Models\User;
use App\Services\MobileMoney\MobileMoneyException;
use App\Services\MobileMoney\MobileMoneyService;
use App\Services\MobileMoney\TransactionStatus;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * Rapprochement manuel (ressource Filament), ignorance, commande planifiée et garde-fous du service.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);
    Notification::fake();

    $plan = Plan::factory()->create(['features' => collect(PlanFeature::cases())->map->value->all()]);
    $this->organisation = Organisation::factory()->create(['plan_id' => $plan->id]);
    $roles = $this->organisation->organisationRoles()->get()->keyBy('slug');

    $this->owner = User::factory()->create();
    $this->organisation->addMember($this->owner, $roles['owner']);

    $this->account = Account::factory()->create([
        'organisation_id' => $this->organisation->id,
        'farm_id' => null,
        'type' => AccountType::MobileMoney,
        'operator' => MobileMoneyOperator::Wave,
        'account_number' => 'M_GESTAVI',
        'initial_balance' => 0,
        'current_balance' => 0,
        'is_active' => true,
    ]);

    $this->customer = Customer::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => null, 'phone' => '0101010101']);

    $this->sale = Sale::factory()->create([
        'organisation_id' => $this->organisation->id,
        'farm_id' => null,
        'customer_id' => $this->customer->id,
        'status' => SaleStatus::Pending,
        'subtotal' => 30000,
        'total' => 30000,
        'paid' => 0,
        'balance' => 30000,
    ]);

    $this->transaction = MobileMoneyTransaction::create([
        'organisation_id' => $this->organisation->id,
        'account_id' => $this->account->id,
        'operator' => MobileMoneyOperator::Wave,
        'external_id' => 'T_MANUAL_001',
        'amount' => 30000,
        'payer_phone' => '+2250909090909',
        'payer_name' => 'Client inconnu',
        'occurred_at' => now()->subHour(),
        'status' => TransactionStatus::Received,
        'raw_payload' => ['source' => 'test'],
    ]);

    actingAs($this->owner);
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($this->organisation);
});

it('rapproche manuellement depuis la liste et crée le paiement Wave sur la facture choisie', function () {
    Livewire::test(ListMobileMoneyTransactions::class)
        ->assertCanSeeTableRecords([$this->transaction])
        ->callAction(TestAction::make('match')->table($this->transaction), data: [
            'sale_id' => $this->sale->id,
            'account_id' => $this->account->id,
        ])
        ->assertHasNoActionErrors();

    $this->transaction->refresh();
    $this->sale->refresh();

    expect($this->transaction->status)->toBe(TransactionStatus::Matched)
        ->and($this->transaction->matched_by)->toBe($this->owner->id)
        ->and($this->transaction->payment->method)->toBe(PaymentMethod::Wave)
        ->and((float) $this->transaction->payment->amount)->toBe(30000.0)
        ->and($this->sale->status)->toBe(SaleStatus::Paid)
        ->and((float) $this->account->fresh()->current_balance)->toBe(30000.0);
});

it('un encaissement partiel est accepté sur une facture au solde supérieur', function () {
    $this->transaction->update(['amount' => 10000]);

    $payment = app(MobileMoneyService::class)->match($this->transaction, $this->sale, $this->account->id, $this->owner);

    expect((float) $payment->amount)->toBe(10000.0)
        ->and($this->sale->fresh()->status)->toBe(SaleStatus::Partial)
        ->and((float) $this->sale->fresh()->balance)->toBe(20000.0);
});

it('refuse de rapprocher une facture au solde insuffisant ou une transaction déjà traitée', function () {
    $this->sale->update(['balance' => 5000, 'paid' => 25000, 'status' => SaleStatus::Partial]);

    expect(fn () => app(MobileMoneyService::class)->match($this->transaction, $this->sale))
        ->toThrow(MobileMoneyException::class);

    app(MobileMoneyService::class)->ignore($this->transaction, $this->owner);

    expect(fn () => app(MobileMoneyService::class)->match($this->transaction->fresh(), $this->sale))
        ->toThrow(MobileMoneyException::class);
});

it('ignore un encaissement depuis la liste', function () {
    Livewire::test(ListMobileMoneyTransactions::class)
        ->callAction(TestAction::make('ignore')->table($this->transaction))
        ->assertHasNoActionErrors();

    expect($this->transaction->fresh()->status)->toBe(TransactionStatus::Ignored)
        ->and($this->transaction->fresh()->matched_by)->toBe($this->owner->id)
        ->and($this->sale->fresh()->status)->toBe(SaleStatus::Pending);
});

it('la commande planifiée rapproche les transactions reçues devenues rapprochables', function () {
    // Le client renseigne son téléphone après coup : la retentative aboutit
    $this->customer->update(['phone' => '0909090909']);
    $this->transaction->forceFill(['created_at' => now()->subMinutes(30)])->save();

    // Transaction trop récente : non examinée
    MobileMoneyTransaction::create([
        'organisation_id' => $this->organisation->id,
        'account_id' => $this->account->id,
        'operator' => MobileMoneyOperator::Wave,
        'external_id' => 'T_FRESH',
        'amount' => 999,
        'occurred_at' => now(),
        'status' => TransactionStatus::Received,
    ]);

    $this->artisan('gestavi:reconcile-mobile-money')
        ->expectsOutputToContain('1 transaction(s) examinée(s), 1 rapprochée(s)')
        ->assertSuccessful();

    expect($this->transaction->fresh()->status)->toBe(TransactionStatus::Matched)
        ->and(MobileMoneyTransaction::query()->where('external_id', 'T_FRESH')->value('status'))->toBe(TransactionStatus::Received);
});

it('les permissions mobile_money existent et sont portées par les rôles système', function () {
    expect(MobileMoneyTransactionResource::canViewAny())->toBeTrue()
        ->and(MobileMoneyTransactionResource::canPerformAction('match'))->toBeTrue()
        ->and(MobileMoneyTransactionResource::canCreate())->toBeFalse();

    $admin = $this->organisation->organisationRoles()->where('slug', 'admin')->first();
    $manager = $this->organisation->organisationRoles()->where('slug', 'manager')->first();

    expect($admin->hasPermission('mobile_money.match'))->toBeTrue()
        ->and($manager->hasPermission('mobile_money.view'))->toBeTrue()
        ->and($manager->hasPermission('mobile_money.match'))->toBeFalse();
});
