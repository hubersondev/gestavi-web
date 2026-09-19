<?php

use App\Enums\SaleStatus;
use App\Enums\VaccinationStatus;
use App\Models\AlertDispatch;
use App\Models\Building;
use App\Models\DailyRecord;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\Organisation;
use App\Models\Sale;
use App\Models\Stock;
use App\Models\User;
use App\Models\Vaccination;
use App\Notifications\OrganisationAlertNotification;
use Database\Seeders\OrganisationPermissionSeeder;
use Illuminate\Support\Facades\Notification;

/**
 * Commandes gestavi:alert-* : destinataires à accès global, idempotence quotidienne,
 * contenu des alertes et canaux de notification.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);
    Notification::fake();

    $this->organisation = Organisation::factory()->create();
    $roles = $this->organisation->organisationRoles()->get()->keyBy('slug');

    $this->owner = User::factory()->create();
    $this->employee = User::factory()->create();
    $this->organisation->addMember($this->owner, $roles['owner']);
    $this->organisation->addMember($this->employee, $roles['employee']);

    $this->farm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);
    $building = Building::factory()->create(['farm_id' => $this->farm->id]);

    $this->flock = Flock::factory()->active()->create([
        'building_id' => $building->id,
        'start_date' => now()->subDays(20),
        'age_at_arrival' => 0,
    ]);
});

it('alerte le propriétaire (pas l\'employé) des vaccinations en retard, une seule fois par jour', function () {
    Vaccination::create([
        'flock_id' => $this->flock->id,
        'treatment_name' => 'Gumboro',
        'treatment_type' => 'vaccin',
        'scheduled_date' => now()->subDays(3)->toDateString(),
        'status' => VaccinationStatus::Pending,
    ]);

    $this->artisan('gestavi:alert-overdue-vaccinations')->assertSuccessful();

    Notification::assertSentTo($this->owner, OrganisationAlertNotification::class, function (OrganisationAlertNotification $notification): bool {
        return $notification->alertKey === 'overdue_vaccinations'
            && str_contains($notification->title, '1 vaccination(s) en retard')
            && str_contains($notification->lines[0], 'Gumboro');
    });
    Notification::assertNotSentTo($this->employee, OrganisationAlertNotification::class);

    // Deuxième exécution le même jour : aucune nouvelle notification
    $this->artisan('gestavi:alert-overdue-vaccinations')->assertSuccessful();

    Notification::assertSentToTimes($this->owner, OrganisationAlertNotification::class, 1);
    expect(AlertDispatch::query()->where('alert_key', 'overdue_vaccinations')->count())->toBe(1);
});

it('signale les bandes actives sans relevé pour la veille', function () {
    $recorded = Flock::factory()->active()->create([
        'building_id' => $this->flock->building_id,
        'start_date' => now()->subDays(20),
    ]);
    DailyRecord::create(['flock_id' => $recorded->id, 'record_date' => now()->subDay()->toDateString()]);

    $this->artisan('gestavi:alert-missing-daily-records')->assertSuccessful();

    Notification::assertSentTo($this->owner, OrganisationAlertNotification::class, function (OrganisationAlertNotification $notification) use ($recorded): bool {
        return $notification->alertKey === 'missing_daily_records'
            && str_contains($notification->title, '1 bande(s) sans relevé')
            && str_contains($notification->lines[0], $this->flock->code)
            && ! str_contains(implode(' ', $notification->lines), $recorded->code);
    });
});

it('signale les factures échues et les stocks sous le seuil', function () {
    Sale::factory()->create([
        'organisation_id' => $this->organisation->id,
        'status' => SaleStatus::Pending,
        'total' => 50000,
        'paid' => 0,
        'balance' => 50000,
        'due_date' => now()->subDays(5),
    ]);
    Sale::factory()->create([
        'organisation_id' => $this->organisation->id,
        'status' => SaleStatus::Pending,
        'total' => 10000,
        'balance' => 10000,
        'due_date' => now()->addDays(5),
    ]);
    Stock::factory()->lowStock()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id]);

    $this->artisan('gestavi:alert-overdue-invoices')->assertSuccessful();
    $this->artisan('gestavi:alert-low-stock')->assertSuccessful();

    Notification::assertSentTo($this->owner, OrganisationAlertNotification::class, fn (OrganisationAlertNotification $n): bool => $n->alertKey === 'overdue_invoices'
        && str_contains($n->title, '1 facture(s) échue(s)')
        && str_contains($n->title, '50 000 XOF'));

    Notification::assertSentTo($this->owner, OrganisationAlertNotification::class, fn (OrganisationAlertNotification $n): bool => $n->alertKey === 'low_stock'
        && str_contains($n->title, '1 produit(s) sous le seuil'));
});

it('n\'envoie rien quand il n\'y a rien à signaler', function () {
    $this->artisan('gestavi:alert-overdue-invoices')->assertSuccessful();

    Notification::assertNothingSent();
    expect(AlertDispatch::query()->count())->toBe(0);
});

it('utilise la base Filament et n\'ajoute le mail que si un mailer réel est configuré', function () {
    $notification = new OrganisationAlertNotification('Titre', ['ligne 1'], 'test', $this->organisation->id);

    config(['mail.default' => 'log']);
    expect($notification->via($this->owner))->toBe(['database']);

    config(['mail.default' => 'smtp']);
    expect($notification->via($this->owner))->toBe(['database', 'mail']);

    $payload = $notification->toDatabase($this->owner);

    expect($payload['title'])->toBe('Titre')
        ->and($payload['format'])->toBe('filament')
        ->and($payload['alert_key'])->toBe('test')
        ->and($notification->databaseType($this->owner))->toBe(\Filament\Notifications\DatabaseNotification::class);
});
