<?php

use App\Filament\Farm\Resources\DailyRecords\DailyRecordResource;
use App\Models\Account;
use App\Models\DailyRecord;
use App\Models\Flock;
use App\Models\Organisation;
use App\Models\User;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

/**
 * Piste d'audit, protection de l'historique et correction rétroactive bornée.
 */
beforeEach(function () {
    // Les permissions doivent exister avant la création de l'organisation (rôles système)
    $this->seed(OrganisationPermissionSeeder::class);

    $this->user = User::factory()->create();
    actingAs($this->user);

    $this->organisation = Organisation::factory()->create();
});

it('journalise les écritures de trésorerie avec leur auteur', function () {
    $account = Account::factory()->create(['organisation_id' => $this->organisation->id, 'initial_balance' => 0, 'current_balance' => 0, 'is_active' => true]);

    $transaction = $account->credit(2500, 'Encaissement test');

    $activity = Activity::query()
        ->where('subject_type', $transaction->getMorphClass())
        ->where('subject_id', $transaction->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->user->id)
        ->and($activity->log_name)->toBe('gestavi')
        ->and((float) data_get($activity->properties, 'attributes.amount'))->toBe(2500.0);
});

it('conserve les valeurs avant et après une correction de relevé', function () {
    $flock = Flock::factory()->active()->create(['quantity_started' => 500, 'quantity_current' => 500, 'start_date' => now()->subDays(5)]);
    $record = DailyRecord::create(['flock_id' => $flock->id, 'record_date' => now()->subDay()->toDateString(), 'mortality' => 3]);

    $record->update(['mortality' => 8, 'correction_reason' => 'Recomptage du soir']);

    $activity = Activity::query()
        ->where('subject_type', $record->getMorphClass())
        ->where('subject_id', $record->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and((int) data_get($activity->properties, 'old.mortality'))->toBe(3)
        ->and((int) data_get($activity->properties, 'attributes.mortality'))->toBe(8)
        ->and(data_get($activity->properties, 'attributes.correction_reason'))->toBe('Recomptage du soir');
});

it('interdit la suppression physique d\'un compte ayant des transactions', function () {
    $account = Account::factory()->create(['organisation_id' => $this->organisation->id, 'initial_balance' => 0, 'current_balance' => 0, 'is_active' => true]);
    $account->credit(100, 'Test');

    expect(fn () => $account->forceDelete())->toThrow(QueryException::class);
});

it('la suppression d\'une bande est logique et laisse ses relevés intacts', function () {
    $flock = Flock::factory()->active()->create(['quantity_started' => 100, 'quantity_current' => 100, 'start_date' => now()->subDays(5)]);
    DailyRecord::create(['flock_id' => $flock->id, 'record_date' => now()->toDateString(), 'mortality' => 1]);

    $flock->delete();

    expect(Flock::query()->find($flock->id))->toBeNull()
        ->and(Flock::withTrashed()->find($flock->id))->not->toBeNull()
        ->and(DailyRecord::query()->where('flock_id', $flock->id)->count())->toBe(1);
});

it('borne la correction rétroactive des relevés à 7 jours pour un manager', function () {
    $roles = $this->organisation->organisationRoles()->get()->keyBy('slug');

    $manager = User::factory()->create();
    $this->organisation->addMember($manager, $roles['manager']);
    $admin = User::factory()->create();
    $this->organisation->addMember($admin, $roles['admin']);

    Filament::setCurrentPanel(Filament::getPanel('farm'));

    $flock = Flock::factory()->active()->create(['quantity_started' => 100, 'quantity_current' => 100, 'start_date' => now()->subDays(30)]);
    $recent = DailyRecord::create(['flock_id' => $flock->id, 'record_date' => now()->subDays(2)->toDateString()]);
    $old = DailyRecord::create(['flock_id' => $flock->id, 'record_date' => now()->subDays(20)->toDateString()]);

    actingAs($manager);
    Filament::setTenant($this->organisation);
    cache()->driver('array')->flush();

    expect(DailyRecordResource::canEdit($recent))->toBeTrue()
        ->and(DailyRecordResource::canEdit($old))->toBeFalse();

    actingAs($admin);
    Filament::setTenant($this->organisation);
    cache()->driver('array')->flush();

    expect(DailyRecordResource::canEdit($old))->toBeTrue();
});
