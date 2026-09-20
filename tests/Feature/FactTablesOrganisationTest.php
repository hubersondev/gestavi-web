<?php

use App\Exceptions\OrganisationMismatchException;
use App\Models\Account;
use App\Models\Building;
use App\Models\DailyRecord;
use App\Models\Employee;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\Organisation;
use App\Models\Payroll;
use App\Models\Sale;
use App\Models\Stock;
use Illuminate\Database\QueryException;

/**
 * Discriminant unique : organisation_id et farm_id sont déduits du parent métier
 * et toute incohérence entre organisations est refusée (application puis base).
 */
beforeEach(function () {
    $this->organisation = Organisation::factory()->create();
    $this->otherOrganisation = Organisation::factory()->create();
    $this->farm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);
    $this->foreignFarm = Farm::factory()->create(['organisation_id' => $this->otherOrganisation->id]);
    $this->building = Building::factory()->create(['farm_id' => $this->farm->id]);
});

it('remplit organisation et ferme le long de la chaîne bâtiment → bande → relevé', function () {
    expect($this->building->fresh()->organisation_id)->toBe($this->organisation->id);

    $flock = Flock::factory()->active()->create(['building_id' => $this->building->id, 'quantity_started' => 100, 'quantity_current' => 100, 'start_date' => now()->subDays(3)]);

    expect($flock->organisation_id)->toBe($this->organisation->id)
        ->and($flock->farm_id)->toBe($this->farm->id);

    $record = DailyRecord::create(['flock_id' => $flock->id, 'record_date' => now()->toDateString(), 'mortality' => 1]);

    expect($record->organisation_id)->toBe($this->organisation->id)
        ->and($record->farm_id)->toBe($this->farm->id);
});

it('remplit organisation et ferme des écritures depuis le compte, et de la paie depuis l\'employé', function () {
    $account = Account::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'initial_balance' => 0, 'current_balance' => 0, 'is_active' => true]);
    $transaction = $account->credit(500, 'Test');

    expect($transaction->organisation_id)->toBe($this->organisation->id)
        ->and($transaction->farm_id)->toBe($this->farm->id);

    $employee = Employee::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id]);
    $payroll = Payroll::create(['employee_id' => $employee->id, 'period_year' => 2026, 'period_month' => 9, 'worked_days' => 22]);

    expect($payroll->organisation_id)->toBe($this->organisation->id)
        ->and($payroll->farm_id)->toBe($this->farm->id);
});

it('refuse une ferme d\'une autre organisation avant la base', function () {
    expect(fn () => Sale::factory()->create([
        'organisation_id' => $this->organisation->id,
        'farm_id' => $this->foreignFarm->id,
    ]))->toThrow(OrganisationMismatchException::class);

    expect(fn () => Stock::factory()->create([
        'organisation_id' => $this->organisation->id,
        'farm_id' => $this->foreignFarm->id,
    ]))->toThrow(OrganisationMismatchException::class);
});

it('refuse un parent d\'une autre organisation', function () {
    $foreignBuilding = Building::factory()->create(['farm_id' => $this->foreignFarm->id]);

    expect(fn () => Flock::factory()->create([
        'organisation_id' => $this->organisation->id,
        'farm_id' => $this->farm->id,
        'building_id' => $foreignBuilding->id,
    ]))->toThrow(OrganisationMismatchException::class);
});

it('la base elle-même refuse le couple (organisation, ferme) incohérent', function () {
    expect(fn () => Sale::withoutEvents(fn () => Sale::query()->insert([
        'organisation_id' => $this->organisation->id,
        'farm_id' => $this->foreignFarm->id,
        'invoice_number' => 'FAC-TEST-1',
        'sale_date' => now()->toDateString(),
        'status' => 'draft',
        'created_at' => now(),
        'updated_at' => now(),
    ])))->toThrow(QueryException::class);
});
