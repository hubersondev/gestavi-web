<?php

use App\Enums\AttendanceStatus;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\PlanFeature;
use App\Enums\VetVisitType;
use App\Filament\Farm\Resources\Accounts\AccountResource;
use App\Filament\Farm\Resources\Attendances\AttendanceResource;
use App\Filament\Farm\Resources\Contracts\ContractResource;
use App\Filament\Farm\Resources\CreditNotes\CreditNoteResource;
use App\Filament\Farm\Resources\DailyRecords\DailyRecordResource;
use App\Filament\Farm\Resources\Employees\EmployeeResource;
use App\Filament\Farm\Resources\EquipmentMaintenances\EquipmentMaintenanceResource;
use App\Filament\Farm\Resources\Expenses\ExpenseResource;
use App\Filament\Farm\Resources\Leaves\LeaveResource;
use App\Filament\Farm\Resources\Payrolls\PayrollResource;
use App\Filament\Farm\Resources\Sales\SaleResource;
use App\Filament\Farm\Resources\StockLots\StockLotResource;
use App\Filament\Farm\Resources\StockMovements\StockMovementResource;
use App\Filament\Farm\Resources\Stocks\StockResource;
use App\Filament\Farm\Resources\Transactions\TransactionResource;
use App\Filament\Farm\Resources\Vaccinations\VaccinationResource;
use App\Filament\Farm\Resources\VetVisits\VetVisitResource;
use App\Models\Account;
use App\Models\Attendance;
use App\Models\Building;
use App\Models\Contract;
use App\Models\CreditNote;
use App\Models\DailyRecord;
use App\Models\Employee;
use App\Models\Equipment;
use App\Models\EquipmentMaintenance;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\Leave;
use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\Payroll;
use App\Models\Plan;
use App\Models\Sale;
use App\Models\Stock;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vaccination;
use App\Models\VetVisit;
use App\Services\CurrentFarm;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;

/**
 * Cloisonnement par ferme : un membre restreint (employé affecté à la ferme A)
 * ne doit voir que les données de la ferme A, jamais celles de la ferme B ni
 * les enregistrements de niveau organisation (farm_id NULL). Un propriétaire voit tout.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);

    $plan = Plan::factory()->create([
        'features' => collect(PlanFeature::cases())->map->value->all(),
    ]);

    $this->organisation = Organisation::factory()->create(['plan_id' => $plan->id]);
    $roles = $this->organisation->organisationRoles()->get()->keyBy('slug');

    $this->farmA = Farm::factory()->create(['organisation_id' => $this->organisation->id, 'name' => 'Ferme A']);
    $this->farmB = Farm::factory()->create(['organisation_id' => $this->organisation->id, 'name' => 'Ferme B']);

    $this->owner = User::factory()->create();
    $this->organisation->addMember($this->owner, $roles['owner']);

    $this->employee = User::factory()->create();
    $this->organisation->addMember($this->employee, $roles['employee']);

    $membership = OrganisationUser::query()
        ->where('user_id', $this->employee->id)
        ->where('organisation_id', $this->organisation->id)
        ->firstOrFail();
    $membership->farms()->attach($this->farmA->id);

    Filament::setCurrentPanel(Filament::getPanel('farm'));
});

function scopeAs(User $user, Organisation $organisation): void
{
    actingAs($user);
    Filament::setTenant($organisation);
    cache()->driver('array')->flush();
}

/**
 * Crée un enregistrement par ferme (+ un de niveau organisation quand la colonne est nullable)
 * et retourne [ids ferme A, ids ferme B, ids organisation].
 *
 * @return array{0: list<int>, 1: list<int>, 2: list<int>}
 */
function seedPerFarm(callable $factory, bool $withOrganisationLevel = true): array
{
    $a = [$factory(test()->farmA->id)->id];
    $b = [$factory(test()->farmB->id)->id];
    $org = $withOrganisationLevel ? [$factory(null)->id] : [];

    return [$a, $b, $org];
}

dataset('ressources cloisonnées', [
    'Sale' => [
        fn () => seedPerFarm(fn (?int $farmId) => Sale::factory()->create(['organisation_id' => test()->organisation->id, 'farm_id' => $farmId])),
        fn () => SaleResource::scopeEloquentQueryToTenant(Sale::query(), test()->organisation),
    ],
    'Account' => [
        fn () => seedPerFarm(fn (?int $farmId) => Account::factory()->create(['organisation_id' => test()->organisation->id, 'farm_id' => $farmId])),
        fn () => AccountResource::scopeEloquentQueryToTenant(Account::query(), test()->organisation),
    ],
    'Expense' => [
        fn () => seedPerFarm(fn (?int $farmId) => Expense::factory()->create(['organisation_id' => test()->organisation->id, 'farm_id' => $farmId])),
        fn () => ExpenseResource::scopeEloquentQueryToTenant(Expense::query(), test()->organisation),
    ],
    'Stock' => [
        fn () => seedPerFarm(fn (?int $farmId) => Stock::factory()->create(['organisation_id' => test()->organisation->id, 'farm_id' => $farmId])),
        fn () => StockResource::scopeEloquentQueryToTenant(Stock::query(), test()->organisation),
    ],
    'Employee' => [
        fn () => seedPerFarm(fn (?int $farmId) => Employee::factory()->create(['organisation_id' => test()->organisation->id, 'farm_id' => $farmId])),
        fn () => EmployeeResource::scopeEloquentQueryToTenant(Employee::query(), test()->organisation),
    ],
    'Transaction (via compte)' => [
        fn () => seedPerFarm(function (?int $farmId) {
            $account = Account::factory()->create(['organisation_id' => test()->organisation->id, 'farm_id' => $farmId, 'initial_balance' => 0, 'current_balance' => 0]);

            return $account->credit(1000, 'Test cloisonnement');
        }),
        fn () => TransactionResource::scopeEloquentQueryToTenant(Transaction::query(), test()->organisation),
    ],
    'Payroll (via employé)' => [
        fn () => seedPerFarm(function (?int $farmId) {
            $employee = Employee::factory()->create(['organisation_id' => test()->organisation->id, 'farm_id' => $farmId]);

            return Payroll::create(['employee_id' => $employee->id, 'period_year' => 2026, 'period_month' => 1]);
        }),
        fn () => PayrollResource::scopeEloquentQueryToTenant(Payroll::query(), test()->organisation),
    ],
    'StockMovement (via stock)' => [
        fn () => seedPerFarm(function (?int $farmId) {
            $stock = Stock::factory()->create(['organisation_id' => test()->organisation->id, 'farm_id' => $farmId, 'quantity' => 0]);

            return $stock->recordIn(10);
        }),
        fn () => StockMovementResource::scopeEloquentQueryToTenant(StockMovement::query(), test()->organisation),
    ],
    'VetVisit' => [
        fn () => seedPerFarm(fn (?int $farmId) => VetVisit::create([
            'farm_id' => $farmId,
            'visit_date' => now()->toDateString(),
            'visit_type' => VetVisitType::Routine,
        ]), withOrganisationLevel: false),
        fn () => VetVisitResource::scopeEloquentQueryToTenant(VetVisit::query(), test()->organisation),
    ],
    // ─── Tables scopées directement depuis l'action 32 (organisation_id / farm_id dénormalisés) ───
    'DailyRecord (via bande)' => [
        fn () => seedPerFarm(fn (?int $farmId) => DailyRecord::create([
            'flock_id' => flockOnFarm($farmId)->id,
            'record_date' => now()->toDateString(),
        ]), withOrganisationLevel: false),
        fn () => DailyRecordResource::scopeEloquentQueryToTenant(DailyRecord::query(), test()->organisation),
    ],
    'Vaccination (via bande)' => [
        fn () => seedPerFarm(fn (?int $farmId) => Vaccination::create([
            'flock_id' => flockOnFarm($farmId)->id,
            'treatment_name' => 'Newcastle',
            'treatment_type' => 'vaccin',
            'scheduled_date' => now()->toDateString(),
        ]), withOrganisationLevel: false),
        fn () => VaccinationResource::scopeEloquentQueryToTenant(Vaccination::query(), test()->organisation),
    ],
    'Contract (via employé)' => [
        fn () => seedPerFarm(fn (?int $farmId) => Contract::factory()->create(['employee_id' => employeeOnFarm($farmId)->id]), withOrganisationLevel: false),
        fn () => ContractResource::scopeEloquentQueryToTenant(Contract::query(), test()->organisation),
    ],
    'Leave (via employé)' => [
        fn () => seedPerFarm(fn (?int $farmId) => Leave::create([
            'employee_id' => employeeOnFarm($farmId)->id,
            'type' => LeaveType::Annual,
            'status' => LeaveStatus::Pending,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'days_count' => 2,
            'reason' => 'Test cloisonnement',
        ]), withOrganisationLevel: false),
        fn () => LeaveResource::scopeEloquentQueryToTenant(Leave::query(), test()->organisation),
    ],
    'Attendance (via employé)' => [
        fn () => seedPerFarm(fn (?int $farmId) => Attendance::create([
            'employee_id' => employeeOnFarm($farmId)->id,
            'attendance_date' => now()->toDateString(),
            'status' => AttendanceStatus::Present,
        ]), withOrganisationLevel: false),
        fn () => AttendanceResource::scopeEloquentQueryToTenant(Attendance::query(), test()->organisation),
    ],
    'StockLot (via stock)' => [
        fn () => seedPerFarm(fn (?int $farmId) => Stock::factory()
            ->create(['organisation_id' => test()->organisation->id, 'farm_id' => $farmId, 'quantity' => 0])
            ->lots()->create([
                'lot_number' => 'LOT-'.uniqid(),
                'reception_date' => now()->toDateString(),
                'initial_quantity' => 10,
                'unit_price' => 100,
            ]), withOrganisationLevel: false),
        fn () => StockLotResource::scopeEloquentQueryToTenant(StockLot::query(), test()->organisation),
    ],
    'CreditNote (via facture)' => [
        fn () => seedPerFarm(fn (?int $farmId) => CreditNote::factory()->create([
            'organisation_id' => test()->organisation->id,
            'sale_id' => Sale::factory()->create(['organisation_id' => test()->organisation->id, 'farm_id' => $farmId])->id,
        ]), withOrganisationLevel: false),
        fn () => CreditNoteResource::scopeEloquentQueryToTenant(CreditNote::query(), test()->organisation),
    ],
    'EquipmentMaintenance (via équipement)' => [
        fn () => seedPerFarm(fn (?int $farmId) => EquipmentMaintenance::factory()->create([
            'equipment_id' => Equipment::factory()->create([
                'building_id' => Building::factory()->create(['farm_id' => $farmId])->id,
            ])->id,
        ]), withOrganisationLevel: false),
        fn () => EquipmentMaintenanceResource::scopeEloquentQueryToTenant(EquipmentMaintenance::query(), test()->organisation),
    ],
]);

/**
 * Bande active rattachée à une ferme (organisation_id / farm_id se remplissent depuis le bâtiment).
 */
function flockOnFarm(int $farmId): Flock
{
    return Flock::factory()->active()->create([
        'building_id' => Building::factory()->create(['farm_id' => $farmId])->id,
        'quantity_started' => 100,
        'quantity_current' => 100,
        'start_date' => now()->subDays(5),
    ]);
}

function employeeOnFarm(int $farmId): Employee
{
    return Employee::factory()->create(['organisation_id' => test()->organisation->id, 'farm_id' => $farmId]);
}

it('un employé affecté à la ferme A ne voit que la ferme A', function (Closure $seed, Closure $scoped) {
    [$idsA, $idsB, $idsOrg] = $seed();

    scopeAs($this->employee, $this->organisation);

    $visible = $scoped()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();

    expect($visible)->toBe(collect($idsA)->sort()->values()->all());
})->with('ressources cloisonnées');

it('un propriétaire voit les deux fermes et le niveau organisation', function (Closure $seed, Closure $scoped) {
    [$idsA, $idsB, $idsOrg] = $seed();

    scopeAs($this->owner, $this->organisation);

    $visible = $scoped()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
    $expected = collect([...$idsA, ...$idsB, ...$idsOrg])->sort()->values()->all();

    expect($visible)->toBe($expected);
})->with('ressources cloisonnées');

it('un propriétaire ayant sélectionné la ferme B ne voit que la ferme B et le niveau organisation', function () {
    [$idsA, $idsB, $idsOrg] = seedPerFarm(fn (?int $farmId) => Sale::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $farmId]));

    scopeAs($this->owner, $this->organisation);

    $membership = OrganisationUser::query()
        ->where('user_id', $this->owner->id)
        ->where('organisation_id', $this->organisation->id)
        ->firstOrFail();

    app(CurrentFarm::class)->set($membership, $this->farmB->id);

    $visible = SaleResource::scopeEloquentQueryToTenant(Sale::query(), $this->organisation)
        ->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();

    expect($visible)->toBe(collect([...$idsB, ...$idsOrg])->sort()->values()->all());
});

it('accessibleFarmIds reflète le rôle et la sélection', function () {
    $currentFarm = app(CurrentFarm::class);

    $ownerMembership = OrganisationUser::query()->where('user_id', $this->owner->id)->firstOrFail();
    $employeeMembership = OrganisationUser::query()->where('user_id', $this->employee->id)->firstOrFail();

    expect($currentFarm->accessibleFarmIds($ownerMembership))->toBeNull()
        ->and($currentFarm->accessibleFarmIds($employeeMembership))->toBe([$this->farmA->id]);

    $currentFarm->set($ownerMembership, $this->farmA->id);

    expect($currentFarm->accessibleFarmIds($ownerMembership))->toBe([$this->farmA->id]);
});

it('sans membership, aucune donnée n\'est renvoyée', function () {
    Sale::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farmA->id]);

    $stranger = User::factory()->create();
    scopeAs($stranger, $this->organisation);

    expect(SaleResource::scopeEloquentQueryToTenant(Sale::query(), $this->organisation)->count())->toBe(0);
});
