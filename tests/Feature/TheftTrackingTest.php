<?php

use App\Enums\EquipmentMovementType;
use App\Enums\StockMovementReason;
use App\Enums\TransactionCategory;
use App\Filament\Farm\Pages\Reports\LossesReportPage;
use App\Models\Account;
use App\Models\Building;
use App\Models\DailyRecord;
use App\Models\Equipment;
use App\Models\EquipmentMovement;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\JournalEntry;
use App\Models\Organisation;
use App\Models\Stock;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\OrganisationAlertNotification;
use App\Services\TheftAlert;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/**
 * Vols : sujets disparus sur un relevé, sorties de stock, matériel et caisse ; alerte critique
 * aux responsables ; comptabilisation en charge diverse ; rapport « Pertes et vols ».
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);
    Notification::fake();
    $this->organisation = Organisation::factory()->create();
    $roles = $this->organisation->organisationRoles()->get()->keyBy('slug');
    $this->owner = User::factory()->create();
    $this->organisation->addMember($this->owner, $roles['owner']);
    $this->farm = Farm::factory()->create(['organisation_id' => $this->organisation->id, 'name' => 'Ferme A']);
    $this->building = Building::factory()->create(['farm_id' => $this->farm->id]);
    $this->flock = Flock::factory()->active()->create([
        'building_id' => $this->building->id,
        'quantity_started' => 1000,
        'quantity_current' => 1000,
        'start_date' => now()->subDays(30),
    ]);
});

function theftAlertSentTo(User $user, string $titleContains): void
{
    Notification::assertSentTo($user, OrganisationAlertNotification::class, fn (OrganisationAlertNotification $n): bool => $n->alertKey === TheftAlert::ALERT_KEY
        && $n->status === 'danger'
        && str_contains($n->title, $titleContains));
}

it('retire les sujets volés de l\'effectif sans toucher au taux de mortalité, et alerte les responsables', function () {
    $record = DailyRecord::create([
        'organisation_id' => $this->organisation->id,
        'farm_id' => $this->farm->id,
        'flock_id' => $this->flock->id,
        'record_date' => now()->toDateString(),
        'mortality' => 10,
        'culls' => 0,
        'thefts' => 25,
    ]);

    $flock = $this->flock->fresh();

    expect($flock->quantity_current)->toBe(965)
        ->and($flock->getMortalityRate())->toBe(1.0)
        ->and($flock->getLossCount())->toBe(35)
        ->and($flock->birdsPresentOn(now()))->toBe(965);

    theftAlertSentTo($this->owner, 'Vol de 25 sujet(s)');

    // Une correction à la hausse alerte de l'écart ; à la baisse, rien
    $record->update(['thefts' => 30]);
    expect($this->flock->fresh()->quantity_current)->toBe(960);
    theftAlertSentTo($this->owner, 'Vol de 5 sujet(s)');

    // Les pertes ne peuvent pas dépasser l'effectif
    expect(fn () => DailyRecord::create([
        'organisation_id' => $this->organisation->id,
        'farm_id' => $this->farm->id,
        'flock_id' => $this->flock->id,
        'record_date' => now()->subDay()->toDateString(),
        'thefts' => 5000,
    ]))->toThrow(InvalidArgumentException::class);
});

it('une sortie de stock pour vol alerte avec la valeur, une perte ou casse non', function () {
    $stock = Stock::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'name' => 'Aliment ponte', 'unit' => 'kg', 'quantity' => 500, 'unit_price' => 350]);

    $stock->recordOut(50, StockMovementReason::Loss, null, null, 'Sac éventré');
    Notification::assertNothingSent();

    $stock->recordOut(100, StockMovementReason::Theft, null, null, 'Porte du magasin forcée');

    expect((float) $stock->fresh()->quantity)->toBe(350.0);
    Notification::assertSentTo($this->owner, OrganisationAlertNotification::class, fn (OrganisationAlertNotification $n): bool => $n->alertKey === 'theft'
        && str_contains($n->title, '100 kg de Aliment ponte')
        && str_contains($n->lines[0], '35 000 XOF')
        && str_contains($n->lines[2], 'Porte du magasin'));
});

it('un vol de matériel réforme l\'équipement et alerte', function () {
    $equipment = Equipment::factory()->create(['building_id' => $this->building->id, 'name' => 'Groupe électrogène', 'status' => 'in_service', 'purchase_price' => 850000, 'current_value' => 600000]);

    EquipmentMovement::create([
        'equipment_id' => $equipment->id,
        'moved_by' => $this->owner->id,
        'from_building_id' => $this->building->id,
        'movement_type' => EquipmentMovementType::Theft->value,
        'movement_date' => now(),
        'reason' => 'Disparu pendant la nuit',
    ]);

    expect($equipment->fresh()->status)->toBe('disposed')
        ->and($equipment->fresh()->building_id)->toBeNull();

    Notification::assertSentTo($this->owner, OrganisationAlertNotification::class, fn (OrganisationAlertNotification $n): bool => $n->alertKey === 'theft'
        && str_contains($n->title, 'Groupe électrogène')
        && str_contains($n->lines[0], '600 000 XOF'));
});

it('un vol de caisse est comptabilisé en charge diverse (658) et alerte', function () {
    $cash = Account::factory()->cash()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'initial_balance' => 0, 'current_balance' => 0, 'is_active' => true]);
    $cash->credit(200000, 'Vente du jour', TransactionCategory::Sale);

    $theft = $cash->debit(75000, 'Caisse forcée dans la nuit', TransactionCategory::Theft);

    expect((float) $cash->fresh()->current_balance)->toBe(125000.0);

    $entry = JournalEntry::query()->where('reference_type', Transaction::class)->where('reference_id', $theft->id)->sole();
    $lines = $entry->lines()->get()->keyBy(fn ($line) => $line->chartAccount->code ?? $line->chart_account_code ?? $line->account_code);
    $codes = $entry->lines()->get()->map(fn ($line) => (string) ($line->chartAccount->code ?? $line->chart_account_code ?? $line->account_code))->all();

    expect($entry->isBalanced())->toBeTrue()
        ->and($codes)->toContain('658')
        ->and($codes)->toContain('571');

    Notification::assertSentTo($this->owner, OrganisationAlertNotification::class, fn (OrganisationAlertNotification $n): bool => $n->alertKey === 'theft'
        && str_contains($n->title, '75 000 XOF')
        && str_contains($n->lines[1], 'Caisse forcée'));
});

it('le rapport « Pertes et vols » consolide sujets, stocks, matériel et caisse', function () {
    DailyRecord::create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'flock_id' => $this->flock->id, 'record_date' => now()->toDateString(), 'mortality' => 8, 'culls' => 2, 'thefts' => 20]);
    $stock = Stock::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'quantity' => 500, 'unit_price' => 100]);
    $stock->recordOut(30, StockMovementReason::Loss);
    $stock->recordOut(70, StockMovementReason::Theft);
    $equipment = Equipment::factory()->create(['building_id' => $this->building->id, 'status' => 'in_service', 'purchase_price' => 90000, 'current_value' => 60000]);
    EquipmentMovement::create(['equipment_id' => $equipment->id, 'moved_by' => $this->owner->id, 'movement_type' => 'theft', 'movement_date' => now()]);
    $cash = Account::factory()->cash()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'initial_balance' => 0, 'current_balance' => 0, 'is_active' => true]);
    $cash->credit(100000, 'Apport', TransactionCategory::Opening);
    $cash->debit(15000, 'Vol de caisse', TransactionCategory::Theft);

    $this->actingAs($this->owner);
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($this->organisation);

    $page = Livewire::test(LossesReportPage::class)
        ->set('filters.date_from', now()->startOfMonth()->toDateString())
        ->set('filters.date_to', now()->toDateString())
        ->set('filters.farm_id', null);

    $kpis = $page->instance()->getKpis();

    expect($kpis['mortality'])->toBe(8)
        ->and($kpis['culls'])->toBe(2)
        ->and($kpis['bird_thefts'])->toBe(20)
        ->and($kpis['stock_loss_value'])->toBe(10000.0)
        ->and($kpis['stock_theft_value'])->toBe(7000.0)
        ->and($kpis['equipment_theft_count'])->toBe(1)
        ->and($kpis['equipment_theft_value'])->toBe(60000.0)
        ->and($kpis['cash_theft_value'])->toBe(15000.0)
        ->and($kpis['theft_value'])->toBe(82000.0)
        ->and($page->instance()->getFlockLosses()->first()->thefts)->toBe(20)
        ->and(strlen($page->instance()->buildXlsx()))->toBeGreaterThan(1000);

    $page->assertSee('Sujets volés')->assertSee('Vol de caisse');
});
