<?php

use App\Enums\PlanFeature;
use App\Enums\TransactionCategory;
use App\Enums\TransactionType;
use App\Exceptions\AccountException;
use App\Filament\Farm\Resources\Accounts\Pages\ViewAccount;
use App\Models\Account;
use App\Models\Farm;
use App\Models\JournalEntry;
use App\Models\Organisation;
use App\Models\OrganisationPermission;
use App\Models\Plan;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountService;
use Database\Seeders\ChartAccountSeeder;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * Réapprovisionnement de caisse : transfert depuis un autre compte, apport de fonds et
 * prélèvement de l'exploitant (imputés au compte 104), actions sur la fiche de compte.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);
    $this->seed(ChartAccountSeeder::class);

    $plan = Plan::factory()->create(['features' => collect(PlanFeature::cases())->map->value->all()]);
    $this->organisation = Organisation::factory()->create(['plan_id' => $plan->id]);
    $this->owner = User::factory()->create();
    $this->organisation->addMember($this->owner, 'owner');
    $this->admin = User::factory()->create();
    $this->organisation->addMember($this->admin, 'admin');
    $this->farm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);

    actingAs($this->owner);

    $this->bank = Account::factory()->bank()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'name' => 'Banque', 'initial_balance' => 500000, 'current_balance' => 500000, 'is_active' => true]);
    $this->cash = Account::factory()->cash()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'name' => 'Caisse', 'initial_balance' => 20000, 'current_balance' => 20000, 'is_active' => true]);
    $this->service = app(AccountService::class);
});

function journalCodesFor(Transaction $transaction): array
{
    $entry = JournalEntry::query()->where('reference_type', Transaction::class)->where('reference_id', $transaction->id)->sole();

    expect($entry->isBalanced())->toBeTrue();

    return $entry->lines()->get()->map(fn ($line) => (string) ($line->chartAccount->code ?? $line->chart_account_code ?? $line->account_code))->all();
}

it('réapprovisionne la caisse depuis la banque : transfert daté, frais à la charge de la source', function () {
    $transfer = $this->service->replenish($this->cash, $this->bank->id, 100000, 1500, '2026-10-10', 'Fonds de caisse de la semaine');

    expect($transfer->transfer_date->toDateString())->toBe('2026-10-10')
        ->and($transfer->isCompleted())->toBeTrue()
        ->and((float) $this->cash->fresh()->current_balance)->toBe(120000.0)
        ->and((float) $this->bank->fresh()->current_balance)->toBe(398500.0);

    $in = $this->cash->transactions()->sole();
    $out = $this->bank->transactions()->sole();

    expect($in->category)->toBe(TransactionCategory::TransferIn)
        ->and($in->transaction_date->toDateString())->toBe('2026-10-10')
        ->and((float) $in->amount)->toBe(100000.0)
        ->and($in->reference_number)->toBe($transfer->reference)
        ->and($out->category)->toBe(TransactionCategory::TransferOut)
        ->and((float) $out->amount)->toBe(101500.0)
        ->and($out->transaction_date->toDateString())->toBe('2026-10-10')
        ->and($transfer->description)->toBe('Réapprovisionnement de Caisse');

    expect(fn () => $this->service->replenish($this->cash, $this->bank->id, 1000000))->toThrow(AccountException::class);
    expect(fn () => $this->service->replenish($this->cash, $this->cash->id, 1000))->toThrow(AccountException::class);
});

it('enregistre un apport de fonds crédité en catégorie dédiée et imputé au compte de l\'exploitant (104)', function () {
    $transaction = $this->service->recordContribution($this->cash, 300000, 'Apport de M. Kouassi', '2026-10-05', 'APP-2026-01');

    expect((float) $this->cash->fresh()->current_balance)->toBe(320000.0)
        ->and($transaction->type)->toBe(TransactionType::Credit)
        ->and($transaction->category)->toBe(TransactionCategory::Contribution)
        ->and($transaction->reference_number)->toBe('APP-2026-01')
        ->and($transaction->transaction_date->toDateString())->toBe('2026-10-05')
        ->and($transaction->description)->toBe('Apport de M. Kouassi');

    $codes = journalCodesFor($transaction);
    expect($codes)->toContain('104')->and($codes)->toContain('571');

    $this->cash->update(['is_active' => false]);
    expect(fn () => $this->service->recordContribution($this->cash->fresh(), 1000))->toThrow(AccountException::class);
});

it('refuse un prélèvement supérieur au solde et débite sinon, imputé au compte de l\'exploitant', function () {
    expect(fn () => $this->service->recordWithdrawal($this->cash, 25000))->toThrow(AccountException::class);
    expect(fn () => $this->service->recordWithdrawal($this->cash, 0))->toThrow(AccountException::class);

    $transaction = $this->service->recordWithdrawal($this->cash, 15000);

    expect((float) $this->cash->fresh()->current_balance)->toBe(5000.0)
        ->and($transaction->type)->toBe(TransactionType::Debit)
        ->and($transaction->category)->toBe(TransactionCategory::Withdrawal)
        ->and($transaction->description)->toBe('Prélèvement de l\'exploitant sur Caisse');

    expect(journalCodesFor($transaction))->toContain('104');
});

it('propose Réapprovisionner, Apport et Prélèvement sur la fiche de compte selon la permission', function () {
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($this->organisation);

    Livewire::test(ViewAccount::class, ['record' => $this->cash->getRouteKey()])
        ->assertOk()
        ->assertActionVisible('replenish')
        ->assertActionVisible('contribution')
        ->assertActionVisible('withdrawal')
        ->callAction('replenish', data: ['from_account_id' => $this->bank->id, 'amount' => 50000, 'fees' => 0, 'date' => '2026-10-12'])
        ->assertHasNoActionErrors()
        ->assertNotified()
        ->callAction('contribution', data: ['amount' => 10000, 'date' => '2026-10-12', 'reference' => 'APP-02'])
        ->assertHasNoActionErrors()
        ->callAction('withdrawal', data: ['amount' => 5000, 'date' => '2026-10-12'])
        ->assertHasNoActionErrors();

    expect((float) $this->cash->fresh()->current_balance)->toBe(75000.0)
        ->and((float) $this->bank->fresh()->current_balance)->toBe(450000.0)
        ->and($this->cash->transactions()->where('category', TransactionCategory::Contribution)->where('reference_number', 'APP-02')->exists())->toBeTrue();

    // Un administrateur privé de « accounts.replenish » garde seulement le prélèvement
    $adminRole = $this->organisation->organisationRoles()->where('slug', 'admin')->firstOrFail();
    $adminRole->permissions()->detach(OrganisationPermission::query()->where('slug', 'accounts.replenish')->value('id'));
    $this->admin->forgetMembershipCache();

    actingAs($this->admin);
    Filament::setTenant($this->organisation);

    Livewire::test(ViewAccount::class, ['record' => $this->cash->getRouteKey()])
        ->assertOk()
        ->assertActionHidden('replenish')
        ->assertActionHidden('contribution')
        ->assertActionVisible('withdrawal');
});
