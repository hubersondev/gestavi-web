<?php

use App\Filament\Audit\AuditTrailRelationManager;
use App\Filament\Farm\Pages\AuditLogPage;
use App\Filament\Farm\Resources\Flocks\Pages\ViewFlock;
use App\Filament\Pages\AuditLog as AdminAuditLog;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Building;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\Organisation;
use App\Models\User;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * Journal d'audit consultable : chaque entrée porte l'organisation du sujet, la page du panel
 * Ferme ne montre que les siennes aux responsables, la page Admin montre tout, l'onglet Historique
 * d'une fiche liste ses propres entrées, et le détail avant / après est lisible.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);

    $this->organisation = Organisation::factory()->create();
    $this->owner = User::factory()->create(['name' => 'Jean Propriétaire']);
    $this->organisation->addMember($this->owner, 'owner');
    $this->employee = User::factory()->create();
    $this->organisation->addMember($this->employee, 'employee');
    $this->farm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);

    $this->other = Organisation::factory()->create();
    $this->otherOwner = User::factory()->create();
    $this->other->addMember($this->otherOwner, 'owner');
    $otherFarm = Farm::factory()->create(['organisation_id' => $this->other->id]);

    actingAs($this->owner);
    $building = Building::factory()->create(['farm_id' => $this->farm->id]);
    $this->flock = Flock::factory()->active()->create(['building_id' => $building->id, 'code' => 'LOT-AUDIT', 'quantity_started' => 500, 'quantity_current' => 500]);
    $this->flock->update(['name' => 'Bande renommée', 'quantity_current' => 480]);

    actingAs($this->otherOwner);
    Account::factory()->create(['organisation_id' => $this->other->id, 'farm_id' => $otherFarm->id, 'name' => 'Caisse voisine']);
});

it('porte l\'organisation du sujet sur chaque entrée et décrit l\'action, la référence et les champs modifiés', function () {
    $entries = AuditLog::query()->where('subject_type', Flock::class)->where('subject_id', $this->flock->id)->orderBy('id')->get();

    expect($entries)->toHaveCount(2)
        ->and($entries->pluck('organisation_id')->unique()->all())->toBe([$this->organisation->id])
        ->and($entries[0]->eventLabel())->toBe('Création')
        ->and($entries[0]->subjectTypeLabel())->toBe('Bande')
        ->and($entries[0]->subjectLabel())->toBe('LOT-AUDIT')
        ->and($entries[0]->causerLabel())->toBe('Jean Propriétaire')
        ->and($entries[1]->eventLabel())->toBe('Modification');

    $diff = collect($entries[1]->diff())->keyBy('field');
    expect($diff->keys()->all())->toContain('name', 'quantity_current')
        ->and($diff['quantity_current']['old'])->toBe(500)
        ->and($diff['quantity_current']['new'])->toBe(480)
        ->and(AuditLog::formatValue(null))->toBe('—')
        ->and(AuditLog::formatValue(true))->toBe('oui');

    expect(AuditLog::query()->where('subject_type', Account::class)->value('organisation_id'))->toBe($this->other->id);
});

it('la page du panel Ferme ne montre que les entrées de l\'organisation, aux responsables seulement', function () {
    actingAs($this->owner);
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($this->organisation);

    expect(AuditLogPage::canAccess())->toBeTrue();

    $mine = AuditLog::query()->forOrganisation($this->organisation->id)->get();
    $theirs = AuditLog::query()->forOrganisation($this->other->id)->get();

    Livewire::test(AuditLogPage::class)
        ->assertOk()
        ->assertCanSeeTableRecords($mine)
        ->assertCanNotSeeTableRecords($theirs)
        ->filterTable('event', ['updated'])
        ->assertCanSeeTableRecords($mine->where('event', 'updated'))
        ->assertCanNotSeeTableRecords($mine->where('event', 'created'));

    actingAs($this->employee);
    expect(AuditLogPage::canAccess())->toBeFalse();
});

it('la page Admin montre toutes les organisations et se filtre par structure', function () {
    $admin = User::factory()->superAdmin()->create();
    actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    expect(AdminAuditLog::canAccess())->toBeTrue();

    Livewire::test(AdminAuditLog::class)
        ->assertOk()
        ->assertCanSeeTableRecords(AuditLog::query()->get())
        ->filterTable('organisation_id', $this->other->id)
        ->assertCanSeeTableRecords(AuditLog::query()->forOrganisation($this->other->id)->get())
        ->assertCanNotSeeTableRecords(AuditLog::query()->forOrganisation($this->organisation->id)->get());
});

it('l\'onglet Historique d\'une fiche liste les entrées de cet enregistrement', function () {
    actingAs($this->owner);
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($this->organisation);

    Livewire::test(AuditTrailRelationManager::class, ['ownerRecord' => $this->flock, 'pageClass' => ViewFlock::class])
        ->assertOk()
        ->assertCanSeeTableRecords($this->flock->activities()->get())
        ->assertCanNotSeeTableRecords(AuditLog::query()->where('subject_type', Account::class)->get());
});
