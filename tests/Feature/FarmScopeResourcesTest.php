<?php

use App\Enums\PlanFeature;
use App\Enums\VetVisitType;
use App\Filament\Farm\Resources\Accounts\AccountResource;
use App\Filament\Farm\Resources\Employees\EmployeeResource;
use App\Filament\Farm\Resources\Expenses\ExpenseResource;
use App\Filament\Farm\Resources\Payrolls\PayrollResource;
use App\Filament\Farm\Resources\Sales\SaleResource;
use App\Filament\Farm\Resources\StockMovements\StockMovementResource;
use App\Filament\Farm\Resources\Stocks\StockResource;
use App\Filament\Farm\Resources\Transactions\TransactionResource;
use App\Filament\Farm\Resources\VetVisits\VetVisitResource;
use App\Models\Account;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\Payroll;
use App\Models\Plan;
use App\Models\Sale;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
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
]);

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
