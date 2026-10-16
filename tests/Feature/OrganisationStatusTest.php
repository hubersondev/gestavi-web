<?php

use App\Enums\OrganisationStatus;
use App\Enums\PlanFeature;
use App\Exceptions\OrganisationReadOnlyException;
use App\Filament\Farm\Pages\OrganisationSuspended;
use App\Filament\Farm\Resources\Flocks\FlockResource;
use App\Filament\Farm\Resources\Flocks\Pages\ListFlocks;
use App\Filament\Resources\Organisations\Pages\ViewOrganisation;
use App\Models\Building;
use App\Models\DailyRecord;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * Statut d'organisation à trois valeurs : lecture seule (consultation sans écriture, panel et API),
 * suspension (plus d'accès), réactivation ; synchronisation avec is_active ; actions admin ;
 * les traitements hors espace tenant (planificateur) continuent d'écrire.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);

    $plan = Plan::factory()->create(['features' => collect(PlanFeature::cases())->map->value->all()]);
    $this->organisation = Organisation::factory()->create(['plan_id' => $plan->id]);
    $this->owner = User::factory()->create();
    $this->organisation->addMember($this->owner, 'owner');
    $this->farm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);
    $building = Building::factory()->create(['farm_id' => $this->farm->id]);
    $this->flock = Flock::factory()->active()->create(['building_id' => $building->id, 'start_date' => now()->subDays(10)]);
    $this->base = '/api/v1/organisations/'.$this->organisation->slug;
});

function enterFarmPanel(User $user, Organisation $organisation): void
{
    actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($organisation);
}

it('en lecture seule, les membres consultent mais aucune écriture ne passe dans l\'espace de l\'organisation', function () {
    $this->organisation->setStatus(OrganisationStatus::ReadOnly, 'Facture impayée');

    expect($this->organisation->fresh()->is_active)->toBeTrue()
        ->and($this->organisation->fresh()->status_changed_at)->not->toBeNull();

    // Hors espace tenant (planificateur, console) : l'écriture reste possible
    $record = DailyRecord::create(['flock_id' => $this->flock->id, 'record_date' => now()->subDay()->toDateString(), 'mortality' => 1]);
    expect($record->exists)->toBeTrue();

    enterFarmPanel($this->owner, $this->organisation->fresh());

    expect(FlockResource::canViewAny())->toBeTrue()
        ->and(FlockResource::canCreate())->toBeFalse()
        ->and(FlockResource::canEdit($this->flock))->toBeFalse()
        ->and(FlockResource::canDelete($this->flock))->toBeFalse();

    Livewire::test(ListFlocks::class)->assertOk();
    get(Filament::getUrl($this->organisation))->assertOk()->assertSee('Lecture seule')->assertSee('Facture impayée');

    expect(fn () => DailyRecord::create(['flock_id' => $this->flock->id, 'record_date' => now()->toDateString(), 'mortality' => 2]))
        ->toThrow(OrganisationReadOnlyException::class, 'lecture seule');
    expect(fn () => $this->flock->update(['name' => 'Renommée']))->toThrow(OrganisationReadOnlyException::class);
    expect(fn () => $record->delete())->toThrow(OrganisationReadOnlyException::class);
    expect(DailyRecord::query()->where('flock_id', $this->flock->id)->count())->toBe(1);

    // Les données propres à l'utilisateur ne sont pas concernées
    $this->owner->update(['name' => 'Jean Modifié']);
    expect($this->owner->fresh()->name)->toBe('Jean Modifié');
});

it('en lecture seule, l\'API sert les lectures et répond 423 aux écritures ; suspendue, elle répond 403', function () {
    $this->organisation->setStatus(OrganisationStatus::ReadOnly, 'Impayé');
    Sanctum::actingAs($this->owner);

    getJson("{$this->base}/flocks")->assertOk();
    postJson("{$this->base}/sync", ['records' => []])
        ->assertStatus(423)
        ->assertJsonPath('status', 'read_only')
        ->assertJsonPath('reason', 'Impayé');

    $this->organisation->setStatus(OrganisationStatus::Suspended, 'Fraude');

    getJson("{$this->base}/flocks")->assertForbidden()->assertJsonPath('status', 'suspended');
});

it('suspendue, l\'espace redirige vers la page de suspension qui affiche le motif', function () {
    $this->organisation->setStatus(OrganisationStatus::Suspended, 'Compte en litige');

    expect($this->organisation->fresh()->is_active)->toBeFalse();

    enterFarmPanel($this->owner, $this->organisation->fresh());

    get(Filament::getUrl($this->organisation))->assertRedirect(OrganisationSuspended::getUrl(panel: 'farm', tenant: $this->organisation));
    get(OrganisationSuspended::getUrl(panel: 'farm', tenant: $this->organisation))->assertOk()->assertSee('suspendu')->assertSee('Compte en litige');
});

it('l\'administrateur change le statut depuis la fiche, et is_active reste cohérent', function () {
    $admin = User::factory()->superAdmin()->create();
    actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(ViewOrganisation::class, ['record' => $this->organisation->getKey()])
        ->callAction('setReadOnly', data: ['reason' => 'Audit en cours'])
        ->assertHasNoActionErrors()
        ->callAction('suspend', data: ['reason' => 'Fraude avérée'])
        ->assertHasNoActionErrors();

    $organisation = $this->organisation->fresh();
    expect($organisation->status)->toBe(OrganisationStatus::Suspended)
        ->and($organisation->status_reason)->toBe('Fraude avérée')
        ->and($organisation->is_active)->toBeFalse();

    Livewire::test(ViewOrganisation::class, ['record' => $this->organisation->getKey()])
        ->callAction('reactivate')
        ->assertHasNoActionErrors();

    $organisation = $this->organisation->fresh();
    expect($organisation->status)->toBe(OrganisationStatus::Active)
        ->and($organisation->status_reason)->toBeNull()
        ->and($organisation->is_active)->toBeTrue();

    // L'ancien drapeau pilote encore le statut pour le code existant
    $organisation->update(['is_active' => false]);
    expect($organisation->fresh()->status)->toBe(OrganisationStatus::Suspended);
    $organisation->update(['is_active' => true]);
    expect($organisation->fresh()->status)->toBe(OrganisationStatus::Active);
});
