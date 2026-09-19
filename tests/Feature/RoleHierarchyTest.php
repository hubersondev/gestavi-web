<?php

use App\Filament\Farm\Pages\Reports\FinancialReportPage;
use App\Filament\Farm\Pages\Reports\ProductionReportPage;
use App\Filament\Farm\Resources\Members\MemberResource;
use App\Filament\Farm\Resources\OrganisationRoles\OrganisationRoleResource;
use App\Filament\Farm\Resources\Payments\PaymentResource;
use App\Filament\Farm\Resources\Transactions\TransactionResource;
use App\Filament\Farm\Widgets\CashFlowWidget;
use App\Filament\Farm\Widgets\ProductionChartWidget;
use App\Models\Account;
use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;

/**
 * Hiérarchie des rôles, verrous sur les journaux comptables et accès
 * aux rapports/widgets selon les permissions d'organisation.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);

    $this->organisation = Organisation::factory()->create();

    $roles = $this->organisation->organisationRoles()->get()->keyBy('slug');
    $this->ownerRole = $roles['owner'];
    $this->adminRole = $roles['admin'];
    $this->employeeRole = $roles['employee'];

    $this->owner = User::factory()->create();
    $this->admin = User::factory()->create();
    $this->employee = User::factory()->create();

    $this->organisation->addMember($this->owner, $this->ownerRole);
    $this->organisation->addMember($this->admin, $this->adminRole);
    $this->organisation->addMember($this->employee, $this->employeeRole);

    Filament::setCurrentPanel(Filament::getPanel('farm'));
});

function actAsMember(User $user, Organisation $organisation): void
{
    actingAs($user);
    Filament::setTenant($organisation);
    cache()->driver('array')->flush();
}

function membershipOf(User $user, Organisation $organisation): OrganisationUser
{
    return OrganisationUser::query()
        ->where('user_id', $user->id)
        ->where('organisation_id', $organisation->id)
        ->firstOrFail();
}

it('les quatre rôles système sont créés pour chaque organisation', function () {
    expect($this->organisation->organisationRoles()->pluck('slug')->sort()->values()->all())
        ->toBe(['admin', 'employee', 'manager', 'owner']);
});

it('un admin ne peut pas modifier ni supprimer un propriétaire', function () {
    actAsMember($this->admin, $this->organisation);

    $ownerMembership = membershipOf($this->owner, $this->organisation);

    expect(MemberResource::canEdit($ownerMembership))->toBeFalse()
        ->and(MemberResource::canDelete($ownerMembership))->toBeFalse();
});

it('un admin peut modifier un employé et un propriétaire peut modifier un admin', function () {
    actAsMember($this->admin, $this->organisation);
    expect(MemberResource::canEdit(membershipOf($this->employee, $this->organisation)))->toBeTrue();

    actAsMember($this->owner, $this->organisation);
    expect(MemberResource::canEdit(membershipOf($this->admin, $this->organisation)))->toBeTrue();
});

it('seul un propriétaire peut modifier un rôle système', function () {
    actAsMember($this->admin, $this->organisation);
    expect(OrganisationRoleResource::canEdit($this->adminRole))->toBeFalse();

    actAsMember($this->owner, $this->organisation);
    expect(OrganisationRoleResource::canEdit($this->adminRole))->toBeTrue();
});

it('les transactions et paiements ne sont jamais modifiables ni supprimables, même par le propriétaire', function () {
    actAsMember($this->owner, $this->organisation);

    $account = Account::factory()->create(['organisation_id' => $this->organisation->id]);
    $transaction = $account->credit(amount: 1000, description: 'Test');

    expect(TransactionResource::canEdit($transaction))->toBeFalse()
        ->and(TransactionResource::canDelete($transaction))->toBeFalse()
        ->and(TransactionResource::canDeleteAny())->toBeFalse();

    $sale = Sale::factory()->create(['organisation_id' => $this->organisation->id]);
    $payment = new Payment(['sale_id' => $sale->id, 'amount' => 100]);

    expect(PaymentResource::canEdit($payment))->toBeFalse()
        ->and(PaymentResource::canDelete($payment))->toBeFalse();
});

it('un employé n\'accède pas au rapport financier ni au widget de trésorerie', function () {
    actAsMember($this->employee, $this->organisation);

    expect(FinancialReportPage::canAccess())->toBeFalse()
        ->and(CashFlowWidget::canView())->toBeFalse()
        ->and(ProductionReportPage::canAccess())->toBeTrue()
        ->and(ProductionChartWidget::canView())->toBeTrue();
});

it('un propriétaire accède à tous les rapports et widgets', function () {
    actAsMember($this->owner, $this->organisation);

    expect(FinancialReportPage::canAccess())->toBeTrue()
        ->and(CashFlowWidget::canView())->toBeTrue();
});
