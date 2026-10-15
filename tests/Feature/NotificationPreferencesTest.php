<?php

use App\Enums\PaymentMethod;
use App\Filament\Farm\Pages\NotificationPreferences;
use App\Models\Account;
use App\Models\Building;
use App\Models\DailyRecord;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\NotificationPreference;
use App\Models\Organisation;
use App\Models\Stock;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\DailyReportNotification;
use App\Notifications\OrganisationAlertNotification;
use App\Services\SaleService;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/**
 * Préférences de notification : page de réglage, canaux filtrés par sujet, rapport quotidien
 * et notification de paiement reçu.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);
    $this->organisation = Organisation::factory()->create();
    $roles = $this->organisation->organisationRoles()->get()->keyBy('slug');
    $this->owner = User::factory()->create(['phone' => '0707070707', 'sms_alerts_enabled' => true]);
    $this->organisation->addMember($this->owner, $roles['owner']);
    $this->farm = Farm::factory()->create(['organisation_id' => $this->organisation->id, 'name' => 'Ferme A']);
});

function alert(string $key, string $status = 'warning'): OrganisationAlertNotification
{
    return new OrganisationAlertNotification(
        title: 'Alerte '.$key,
        lines: ['• détail'],
        alertKey: $key,
        organisationId: test()->organisation->id,
        status: $status,
    );
}

it('sans réglage, les alertes arrivent dans l\'application et par e-mail, et seules les critiques par SMS', function () {
    config(['mail.default' => 'smtp']);

    expect(alert('low_stock')->via($this->owner))->toBe(['database', 'mail'])
        ->and(alert('overdue_invoices', 'danger')->via($this->owner))->toBe(['database', 'mail', SmsChannel::class])
        ->and(alert('payment_received', 'success')->via($this->owner))->toBe(['mail'])
        ->and(alert('report_export')->via($this->owner))->toBe(['database', 'mail']);
});

it('respecte les préférences enregistrées, sujet par sujet et canal par canal', function () {
    config(['mail.default' => 'smtp']);
    NotificationPreference::query()->create([
        'user_id' => $this->owner->id,
        'email_stock' => false,
        'app_alerts' => false,
        'app_payments' => true,
        'sms_critical_alerts' => false,
        'sms_payments' => true,
    ]);
    $this->owner->refresh();

    expect(alert('low_stock')->via($this->owner))->toBe(['database'])
        ->and(alert('missing_daily_records')->via($this->owner))->toBe(['mail'])
        ->and(alert('overdue_invoices', 'danger')->via($this->owner))->toBe(['mail'])
        ->and(alert('payment_received', 'success')->via($this->owner))->toBe(['database', 'mail', SmsChannel::class]);

    $this->owner->notificationPreferences->update(['app_enabled' => false]);
    $this->owner->refresh();

    expect(alert('low_stock')->via($this->owner))->toBe([])
        ->and(alert('payment_received', 'success')->via($this->owner))->toBe(['mail', SmsChannel::class]);
});

it('la page du panel enregistre les préférences et l\'activation des SMS', function () {
    $this->actingAs($this->owner);
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($this->organisation);

    Livewire::test(NotificationPreferences::class)
        ->assertFormSet(['email_daily_report' => true, 'app_payments' => false, 'sms_enabled' => true])
        ->fillForm(['email_daily_report' => false, 'app_payments' => true, 'sms_enabled' => false])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $preference = NotificationPreference::query()->where('user_id', $this->owner->id)->sole();

    expect($preference->email_daily_report)->toBeFalse()
        ->and($preference->app_payments)->toBeTrue()
        ->and($preference->email_alerts)->toBeTrue()
        ->and($this->owner->fresh()->sms_alerts_enabled)->toBeFalse();
});

it('envoie le rapport quotidien aux utilisateurs qui l\'ont gardé, avec l\'activité de la veille', function () {
    config(['mail.default' => 'smtp']);
    Notification::fake();

    $building = Building::factory()->create(['farm_id' => $this->farm->id]);
    $flock = Flock::factory()->active()->create(['building_id' => $building->id, 'quantity_started' => 1000, 'quantity_current' => 1000, 'start_date' => now()->subDays(150)]);
    DailyRecord::create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'flock_id' => $flock->id, 'record_date' => now()->subDay()->toDateString(), 'mortality' => 4, 'culls' => 1, 'eggs_collected' => 850]);
    Stock::factory()->lowStock()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id]);

    $optOut = User::factory()->create();
    $this->organisation->addMember($optOut, 'admin');
    NotificationPreference::query()->create(['user_id' => $optOut->id, 'email_daily_report' => false]);

    $this->artisan('gestavi:daily-report')->assertSuccessful();

    Notification::assertSentTo($this->owner, DailyReportNotification::class, function (DailyReportNotification $notification): bool {
        $farm = collect($notification->farms)->firstWhere('name', 'Ferme A');
        $mail = $notification->toMail(test()->owner);

        return $farm['eggs'] === 850
            && $farm['losses'] === 5
            && $farm['birds'] === 995
            && $farm['low_stocks'] === 1
            && str_contains($mail->subject, 'Rapport quotidien')
            && $notification->via(test()->owner) === ['mail'];
    });
    Notification::assertNotSentTo($optOut, DailyReportNotification::class);
});

it('prévient les responsables d\'un paiement reçu', function () {
    config(['mail.default' => 'smtp']);
    Notification::fake();
    $account = Account::factory()->cash()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'initial_balance' => 0, 'current_balance' => 0]);
    $service = app(SaleService::class);
    $sale = $service->createSale(
        ['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'sale_date' => now()->toDateString()],
        [['product_name' => 'Plateau', 'unit' => 'plateau', 'quantity' => 10, 'unit_price' => 3000]],
    );

    $service->recordPayment($sale->fresh(), 10000, PaymentMethod::Cash, $account->id);

    Notification::assertSentTo($this->owner, OrganisationAlertNotification::class, fn (OrganisationAlertNotification $n): bool => $n->alertKey === 'payment_received'
        && str_contains($n->title, $sale->invoice_number)
        && str_contains($n->lines[0], '10 000 XOF')
        && str_contains($n->lines[2], 'Reste dû : 20 000 XOF'));
});
