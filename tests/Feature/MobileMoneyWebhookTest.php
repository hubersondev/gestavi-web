<?php

use App\Enums\AccountType;
use App\Enums\MobileMoneyOperator;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Account;
use App\Models\Customer;
use App\Models\MobileMoneyTransaction;
use App\Models\Organisation;
use App\Models\Sale;
use App\Models\User;
use App\Notifications\OrganisationAlertNotification;
use App\Services\MobileMoney\TransactionStatus;
use App\Services\MobileMoney\WebhookSignatureVerifier;
use Database\Seeders\OrganisationPermissionSeeder;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\postJson;

/**
 * Webhooks opérateurs : signature, idempotence, résolution de l'organisation par le
 * compte marchand, rapprochement automatique et notification des encaissements orphelins.
 */
function mobileMoneyFixture(string $operator): array
{
    return json_decode(file_get_contents(base_path("tests/Fixtures/mobile-money/{$operator}.json")), true);
}

function postWebhook(string $operator, array $payload, ?string $secret = null, array $query = [])
{
    $body = json_encode($payload);
    $signature = WebhookSignatureVerifier::sign($body, $secret ?? config("mobilemoney.operators.{$operator}.webhook_secret"));

    return postJson('/api/webhooks/mobile-money/'.$operator.($query ? '?'.http_build_query($query) : ''), $payload, [
        'X-Signature' => 'sha256='.$signature,
    ]);
}

beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);
    Notification::fake();

    config([
        'mobilemoney.operators.orange.webhook_secret' => 'secret-orange',
        'mobilemoney.operators.mtn.webhook_secret' => 'secret-mtn',
        'mobilemoney.operators.moov.webhook_secret' => 'secret-moov',
        'mobilemoney.operators.wave.webhook_secret' => 'secret-wave',
    ]);

    $this->organisation = Organisation::factory()->create();
    $roles = $this->organisation->organisationRoles()->get()->keyBy('slug');
    $this->owner = User::factory()->create();
    $this->organisation->addMember($this->owner, $roles['owner']);

    // Compte marchand Orange Money identifié par son numéro
    $this->orangeAccount = Account::factory()->create([
        'organisation_id' => $this->organisation->id,
        'farm_id' => null,
        'type' => AccountType::MobileMoney,
        'operator' => MobileMoneyOperator::Orange,
        'phone_number' => '05 05 05 05 05',
        'initial_balance' => 0,
        'current_balance' => 0,
        'is_active' => true,
    ]);
});

function pendingSale(Organisation $organisation, float $total, ?Customer $customer = null): Sale
{
    return Sale::factory()->create([
        'organisation_id' => $organisation->id,
        'farm_id' => null,
        'customer_id' => $customer?->id,
        'status' => SaleStatus::Pending,
        'subtotal' => $total,
        'total' => $total,
        'paid' => 0,
        'balance' => $total,
    ]);
}

it('refuse une signature invalide ou absente', function () {
    postJson('/api/webhooks/mobile-money/orange', mobileMoneyFixture('orange'), ['X-Signature' => 'sha256=deadbeef'])->assertStatus(401);
    postJson('/api/webhooks/mobile-money/orange', mobileMoneyFixture('orange'))->assertStatus(401);

    expect(MobileMoneyTransaction::query()->count())->toBe(0);
});

it('refuse un webhook quand aucun secret n\'est configuré pour l\'opérateur', function () {
    config(['mobilemoney.operators.moov.webhook_secret' => null]);

    postWebhook('moov', mobileMoneyFixture('moov'), 'nimporte')->assertStatus(401);
});

it('enregistre une notification Orange valide en « à rapprocher » et résout l\'organisation par le compte marchand', function () {
    $response = postWebhook('orange', mobileMoneyFixture('orange'));

    $response->assertStatus(201)->assertJson(['status' => 'ok', 'created' => true, 'transaction_status' => 'received']);

    $transaction = MobileMoneyTransaction::query()->firstOrFail();

    expect($transaction->organisation_id)->toBe($this->organisation->id)
        ->and($transaction->account_id)->toBe($this->orangeAccount->id)
        ->and($transaction->operator)->toBe(MobileMoneyOperator::Orange)
        ->and($transaction->external_id)->toBe('MP260920.1015.A12345')
        ->and((float) $transaction->amount)->toBe(25000.0)
        ->and($transaction->payer_phone)->toBe('+2250707070707')
        ->and($transaction->status)->toBe(TransactionStatus::Received)
        ->and($transaction->raw_payload['txnid'])->toBe('MP260920.1015.A12345');

    // Sans facture correspondante : les responsables sont prévenus
    Notification::assertSentTo($this->owner, OrganisationAlertNotification::class, fn ($n): bool => $n->alertKey === 'mobile_money_unmatched');
});

it('est idempotent sur l\'identifiant externe', function () {
    postWebhook('orange', mobileMoneyFixture('orange'))->assertStatus(201);
    postWebhook('orange', mobileMoneyFixture('orange'))->assertStatus(200)->assertJson(['created' => false]);

    expect(MobileMoneyTransaction::query()->count())->toBe(1);
});

it('rapproche automatiquement la facture unique du même montant dont le client a le téléphone du payeur', function () {
    $customer = Customer::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => null, 'phone' => '0707070707']);
    $sale = pendingSale($this->organisation, 25000, $customer);
    pendingSale($this->organisation, 25000); // même montant mais client comptant : ne doit pas être choisie

    postWebhook('orange', mobileMoneyFixture('orange'))->assertStatus(201)->assertJson(['transaction_status' => 'matched']);

    $transaction = MobileMoneyTransaction::query()->firstOrFail();
    $sale->refresh();

    expect($transaction->status)->toBe(TransactionStatus::Matched)
        ->and($transaction->payment_id)->not->toBeNull()
        ->and($sale->status)->toBe(SaleStatus::Paid)
        ->and((float) $sale->balance)->toBe(0.0)
        ->and($transaction->payment->method)->toBe(PaymentMethod::OrangeMoney)
        ->and($transaction->payment->reference)->toBe('MP260920.1015.A12345')
        ->and($transaction->payment->payment_date->toDateString())->toBe('2026-09-20')
        ->and((float) $this->orangeAccount->fresh()->current_balance)->toBe(25000.0);

    Notification::assertNotSentTo($this->owner, OrganisationAlertNotification::class);
});

it('laisse en « à rapprocher » quand le montant ne correspond à aucune facture du client', function () {
    $customer = Customer::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => null, 'phone' => '0707070707']);
    pendingSale($this->organisation, 40000, $customer);

    postWebhook('orange', mobileMoneyFixture('orange'))->assertStatus(201)->assertJson(['transaction_status' => 'received']);

    Notification::assertSentTo($this->owner, OrganisationAlertNotification::class, fn ($n): bool => $n->alertKey === 'mobile_money_unmatched');
});

it('accepte les formats MTN, Moov et Wave (résolution par numéro marchand, identifiant marchand ou paramètre organisation)', function () {
    Account::factory()->create([
        'organisation_id' => $this->organisation->id, 'farm_id' => null,
        'type' => AccountType::MobileMoney, 'operator' => MobileMoneyOperator::Mtn,
        'phone_number' => '+225 05 01 01 01 01', 'is_active' => true, 'initial_balance' => 0, 'current_balance' => 0,
    ]);
    Account::factory()->create([
        'organisation_id' => $this->organisation->id, 'farm_id' => null,
        'type' => AccountType::MobileMoney, 'operator' => MobileMoneyOperator::Wave,
        'account_number' => 'M_GESTAVI_DEMO', 'is_active' => true, 'initial_balance' => 0, 'current_balance' => 0,
    ]);

    postWebhook('mtn', mobileMoneyFixture('mtn'))->assertStatus(201);
    postWebhook('wave', mobileMoneyFixture('wave'))->assertStatus(201);
    // Moov : aucun compte marchand connu → résolution par le paramètre organisation
    postWebhook('moov', mobileMoneyFixture('moov'), query: ['organisation' => $this->organisation->id])->assertStatus(201);

    expect(MobileMoneyTransaction::query()->where('organisation_id', $this->organisation->id)->count())->toBe(3)
        ->and(MobileMoneyTransaction::query()->where('operator', 'wave')->value('external_id'))->toBe('T_WAVE_0001')
        ->and(MobileMoneyTransaction::query()->where('operator', 'mtn')->value('payer_phone'))->toBe('+2250505050505');
});

it('rejette une notification dont le marchand est inconnu ou le payload incomplet', function () {
    $payload = mobileMoneyFixture('orange');
    $payload['merchant_msisdn'] = '0199999999';
    $payload['txnid'] = 'AUTRE';

    postWebhook('orange', $payload)->assertStatus(422);

    postWebhook('orange', ['status' => 'SUCCESS'])->assertStatus(422);
    postJson('/api/webhooks/mobile-money/inconnu', [])->assertStatus(404);
});
