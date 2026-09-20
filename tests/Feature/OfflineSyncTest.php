<?php

use App\Enums\VaccinationStatus;
use App\Models\Building;
use App\Models\DailyRecord;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\OfflineSyncEntry;
use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\User;
use App\Models\Vaccination;
use Database\Seeders\OrganisationPermissionSeeder;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * Saisie terrain hors-ligne : cloisonnement par ferme, synchronisation idempotente,
 * règles métier appliquées côté serveur.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);

    $this->organisation = Organisation::factory()->create();
    $this->otherOrganisation = Organisation::factory()->create();
    $roles = $this->organisation->organisationRoles()->get()->keyBy('slug');

    $this->owner = User::factory()->create();
    $this->employee = User::factory()->create();
    $this->organisation->addMember($this->owner, $roles['owner']);
    $this->organisation->addMember($this->employee, $roles['employee']);

    $this->farmA = Farm::factory()->create(['organisation_id' => $this->organisation->id]);
    $this->farmB = Farm::factory()->create(['organisation_id' => $this->organisation->id]);

    $employeeMembership = OrganisationUser::query()->where('user_id', $this->employee->id)->where('organisation_id', $this->organisation->id)->first();
    $this->farmA->assignedMembers()->attach($employeeMembership->id);

    $buildingA = Building::factory()->create(['farm_id' => $this->farmA->id]);
    $buildingB = Building::factory()->create(['farm_id' => $this->farmB->id]);

    $this->flockA = Flock::factory()->active()->create(['building_id' => $buildingA->id, 'quantity_started' => 500, 'quantity_current' => 500, 'start_date' => now()->subDays(20)]);
    $this->flockB = Flock::factory()->active()->create(['building_id' => $buildingB->id, 'quantity_started' => 300, 'quantity_current' => 300, 'start_date' => now()->subDays(20)]);

    $foreignBuilding = Building::factory()->create(['farm_id' => Farm::factory()->create(['organisation_id' => $this->otherOrganisation->id])->id]);
    $this->foreignFlock = Flock::factory()->active()->create(['building_id' => $foreignBuilding->id, 'quantity_started' => 100, 'quantity_current' => 100, 'start_date' => now()->subDays(5)]);
});

function syncEntry(array $payload, string $type = 'daily_record', ?string $uuid = null): array
{
    return [
        'client_uuid' => $uuid ?? (string) Str::uuid(),
        'type' => $type,
        'payload' => $payload,
        'recorded_at' => now()->toIso8601String(),
    ];
}

it('la page terrain est réservée aux membres et le membre restreint ne voit que ses bandes', function () {
    $outsider = User::factory()->create();

    actingAs($outsider);
    $this->get(route('offline.page', $this->organisation))->assertForbidden();

    actingAs($this->employee);
    $this->get(route('offline.page', $this->organisation))->assertOk()->assertSee('Saisie terrain');

    $flocks = getJson(route('offline.flocks', $this->organisation))->assertOk()->json('flocks');

    expect(collect($flocks)->pluck('id')->all())->toBe([$this->flockA->id]);

    actingAs($this->owner);
    $flocks = getJson(route('offline.flocks', $this->organisation))->assertOk()->json('flocks');

    expect(collect($flocks)->pluck('id')->sort()->values()->all())->toBe(collect([$this->flockA->id, $this->flockB->id])->sort()->values()->all());
});

it('synchronise un relevé, décrémente l\'effectif et ignore le renvoi du même client_uuid', function () {
    actingAs($this->employee);

    $entry = syncEntry([
        'flock_id' => $this->flockA->id,
        'record_date' => now()->subDay()->toDateString(),
        'mortality' => 7,
        'culls' => 3,
        'feed_consumed_kg' => 55.5,
        'eggs_collected' => 0,
    ]);

    $response = postJson(route('offline.sync', $this->organisation), ['entries' => [$entry]])->assertOk();

    expect($response->json('results.0.status'))->toBe('created')
        ->and($response->json('results.0.id'))->not->toBeNull()
        ->and($this->flockA->fresh()->quantity_current)->toBe(490);

    $record = DailyRecord::query()->find($response->json('results.0.id'));

    expect($record->recorded_by)->toBe($this->employee->id)
        ->and($record->record_date->toDateString())->toBe(now()->subDay()->toDateString())
        ->and((float) $record->feed_consumed_kg)->toBe(55.5);

    // Renvoi (réseau instable) : même client_uuid → doublon, aucun second relevé
    $again = postJson(route('offline.sync', $this->organisation), ['entries' => [$entry]])->assertOk();

    expect($again->json('results.0.status'))->toBe('duplicate')
        ->and(DailyRecord::query()->where('flock_id', $this->flockA->id)->count())->toBe(1)
        ->and(OfflineSyncEntry::query()->count())->toBe(1)
        ->and($this->flockA->fresh()->quantity_current)->toBe(490);
});

it('signale un doublon quand un relevé en ligne existe déjà pour la même date sans l\'écraser', function () {
    DailyRecord::create(['flock_id' => $this->flockA->id, 'record_date' => now()->toDateString(), 'mortality' => 2, 'recorded_by' => $this->owner->id]);

    actingAs($this->employee);

    $response = postJson(route('offline.sync', $this->organisation), ['entries' => [
        syncEntry(['flock_id' => $this->flockA->id, 'record_date' => now()->toDateString(), 'mortality' => 9]),
    ]])->assertOk();

    expect($response->json('results.0.status'))->toBe('duplicate')
        ->and($response->json('results.0.message'))->toContain('existe déjà')
        ->and(DailyRecord::query()->where('flock_id', $this->flockA->id)->count())->toBe(1)
        ->and(DailyRecord::query()->where('flock_id', $this->flockA->id)->value('mortality'))->toBe(2);
});

it('refuse une bande d\'une autre organisation, une bande d\'une ferme non affectée et un outsider', function () {
    actingAs($this->employee);

    $response = postJson(route('offline.sync', $this->organisation), ['entries' => [
        syncEntry(['flock_id' => $this->foreignFlock->id, 'record_date' => now()->toDateString(), 'mortality' => 1]),
        syncEntry(['flock_id' => $this->flockB->id, 'record_date' => now()->toDateString(), 'mortality' => 1]),
    ]])->assertOk();

    expect($response->json('results.0.status'))->toBe('rejected')
        ->and($response->json('results.1.status'))->toBe('rejected')
        ->and($this->foreignFlock->fresh()->quantity_current)->toBe(100)
        ->and($this->flockB->fresh()->quantity_current)->toBe(300);

    actingAs(User::factory()->create());
    postJson(route('offline.sync', $this->organisation), ['entries' => [
        syncEntry(['flock_id' => $this->flockA->id, 'record_date' => now()->toDateString(), 'mortality' => 1]),
    ]])->assertForbidden();
});

it('refuse des pertes supérieures à l\'effectif et une date future, avec un message explicite', function () {
    actingAs($this->employee);

    $response = postJson(route('offline.sync', $this->organisation), ['entries' => [
        syncEntry(['flock_id' => $this->flockA->id, 'record_date' => now()->toDateString(), 'mortality' => 600]),
        syncEntry(['flock_id' => $this->flockA->id, 'record_date' => now()->addDays(2)->toDateString(), 'mortality' => 1]),
    ]])->assertOk();

    expect($response->json('results.0.status'))->toBe('rejected')
        ->and($response->json('results.0.message'))->toContain('dépasse')
        ->and($response->json('results.1.status'))->toBe('rejected')
        ->and($response->json('results.1.message'))->toContain('futur')
        ->and($this->flockA->fresh()->quantity_current)->toBe(500)
        ->and(OfflineSyncEntry::query()->where('status', 'rejected')->count())->toBe(2);
});

it('marque une vaccination effectuée via la synchronisation, une seule fois', function () {
    $vaccination = Vaccination::create([
        'flock_id' => $this->flockA->id,
        'treatment_name' => 'Gumboro',
        'treatment_type' => 'vaccin',
        'scheduled_date' => now()->toDateString(),
        'status' => VaccinationStatus::Pending,
    ]);

    actingAs($this->owner);

    $list = getJson(route('offline.vaccinations', $this->organisation))->assertOk()->json('vaccinations');
    expect(collect($list)->pluck('id')->all())->toContain($vaccination->id);

    $entry = syncEntry(['vaccination_id' => $vaccination->id, 'administered_date' => now()->toDateString(), 'birds_treated' => 495], 'vaccination_done');

    $response = postJson(route('offline.sync', $this->organisation), ['entries' => [$entry]])->assertOk();

    expect($response->json('results.0.status'))->toBe('created')
        ->and($vaccination->fresh()->status)->toBe(VaccinationStatus::Done)
        ->and($vaccination->fresh()->administered_by)->toBe($this->owner->id)
        ->and($vaccination->fresh()->birds_treated)->toBe(495);

    $again = postJson(route('offline.sync', $this->organisation), ['entries' => [
        syncEntry(['vaccination_id' => $vaccination->id, 'administered_date' => now()->toDateString()], 'vaccination_done'),
    ]])->assertOk();

    expect($again->json('results.0.status'))->toBe('duplicate');
});

it('un employé sans permission de vaccination est refusé et le jeton CSRF est rechargeable', function () {
    $vaccination = Vaccination::create([
        'flock_id' => $this->flockA->id,
        'treatment_name' => 'Newcastle',
        'treatment_type' => 'vaccin',
        'scheduled_date' => now()->toDateString(),
        'status' => VaccinationStatus::Pending,
    ]);

    // Le rôle employé possède vaccinations.mark_done ; on le retire pour ce test
    $role = $this->organisation->organisationRoles()->where('slug', 'employee')->first();
    $role->permissions()->detach(\App\Models\OrganisationPermission::query()->where('slug', 'vaccinations.mark_done')->value('id'));

    actingAs($this->employee);

    $response = postJson(route('offline.sync', $this->organisation), ['entries' => [
        syncEntry(['vaccination_id' => $vaccination->id, 'administered_date' => now()->toDateString()], 'vaccination_done'),
    ]])->assertOk();

    expect($response->json('results.0.status'))->toBe('rejected')
        ->and($vaccination->fresh()->status)->toBe(VaccinationStatus::Pending);

    getJson(route('offline.csrf', $this->organisation))->assertOk()->assertJsonStructure(['token']);
    getJson(route('offline.assets'))->assertOk()->assertJsonStructure(['assets', 'version']);
});
