<?php

use App\Enums\BankStatementLineStatus;
use App\Enums\PlanFeature;
use App\Enums\TransactionCategory;
use App\Exceptions\BankReconciliationException;
use App\Exceptions\TransactionException;
use App\Filament\Farm\Resources\BankReconciliations\BankReconciliationResource;
use App\Filament\Farm\Resources\BankReconciliations\Pages\ViewBankReconciliation;
use App\Filament\Farm\Resources\BankReconciliations\RelationManagers\StatementLinesRelationManager;
use App\Filament\Farm\Resources\BankReconciliations\RelationManagers\TransactionsRelationManager;
use App\Models\Account;
use App\Models\BankReconciliation;
use App\Models\Farm;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\User;
use App\Services\BankReconciliationService;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * Rapprochement bancaire par relevé : ouverture, import CSV, correspondance automatique, pointage
 * manuel, clôture à écart nul, fiche Filament et cloisonnement.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);

    $this->plan = Plan::factory()->create(['features' => collect(PlanFeature::cases())->map->value->all()]);
    $this->organisation = Organisation::factory()->create(['plan_id' => $this->plan->id]);
    $this->owner = User::factory()->create();
    $this->organisation->addMember($this->owner, 'owner');
    $this->farm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);

    actingAs($this->owner);

    // Compte de factory : le solde initial est implicite (aucune transaction d'ouverture)
    $this->bank = Account::factory()->bank()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'name' => 'Banque', 'initial_balance' => 500000, 'current_balance' => 500000, 'is_active' => true]);
    $this->t1 = $this->bank->credit(150000, 'Virement client Yao', TransactionCategory::Payment, referenceNumber: 'VIR-2041', date: '2026-09-05');
    $this->t2 = $this->bank->debit(80000, 'Achat aliment', TransactionCategory::Expense, referenceNumber: 'CHQ-118', date: '2026-09-12');
    $this->t3 = $this->bank->debit(25000, 'Frais bancaires', TransactionCategory::Other, date: '2026-09-28');
    // Postérieure au relevé : hors périmètre
    $this->t4 = $this->bank->credit(60000, 'Vente œufs', TransactionCategory::Sale, date: '2026-10-03');

    $this->service = app(BankReconciliationService::class);
});

it('ouvre un rapprochement, calcule le solde pointé et refuse un second brouillon sur le même compte', function () {
    $reconciliation = $this->service->start($this->bank, '2026-09-30', 545000, 'Relevé de septembre');

    expect($reconciliation->isDraft())->toBeTrue()
        ->and($reconciliation->organisation_id)->toBe($this->organisation->id)
        ->and($reconciliation->farm_id)->toBe($this->farm->id)
        ->and($reconciliation->created_by)->toBe($this->owner->id)
        ->and($reconciliation->label())->toBe('Banque — relevé au 30/09/2026');

    $summary = $this->service->summary($reconciliation);

    expect($summary['reconciled_balance'])->toBe(500000.0)
        ->and($summary['difference'])->toBe(45000.0)
        ->and($summary['is_balanced'])->toBeFalse()
        ->and($summary['pending_count'])->toBe(3)
        ->and($summary['pending_amount'])->toBe(45000.0)
        ->and($summary['lines_total'])->toBe(0);

    expect(fn () => $this->service->start($this->bank, '2026-10-31', 600000))->toThrow(BankReconciliationException::class);
});

it('importe un relevé CSV français (point-virgule, débit / crédit, montants avec espaces), rapproche automatiquement et clôture', function () {
    $reconciliation = $this->service->start($this->bank, '2026-09-30', 545000);

    $csv = implode("\n", [
        'Date;Libellé;Débit;Crédit;Référence',
        '05/09/2026;VIR SEPA YAO;;150 000;VIR-2041',
        '14/09/2026;CHEQUE 118;80 000,00;;CHQ-118',
        '28/09/2026;COMMISSIONS;25 000;;',
        '29/09/2026;AGIOS;1 500;;',
        '03/10/2026;VENTE OEUFS;;60 000;',
        'date illisible;X;;10;',
    ]);
    // Extension .txt, comme un fichier envoyé par le navigateur : le format est reconnu au contenu
    $path = tempnam(sys_get_temp_dir(), 'releve').'.txt';
    file_put_contents($path, $csv);

    try {
        $result = $this->service->importStatement($reconciliation, $path, 'releve-septembre.csv');
        // Réimporter le même relevé n'ajoute rien
        $again = $this->service->importStatement($reconciliation, $path);
    } finally {
        @unlink($path);
    }

    expect($result['imported'])->toBe(4)
        ->and($result['duplicates'])->toBe(0)
        ->and($result['after_statement'])->toBe(1)
        ->and($result['errors'])->toHaveCount(1)
        ->and($again['imported'])->toBe(0)
        ->and($again['duplicates'])->toBe(4)
        ->and($reconciliation->fresh()->statement_file)->toBe('releve-septembre.csv');

    $lines = $reconciliation->lines()->orderBy('line_date')->orderBy('id')->get();
    expect($lines)->toHaveCount(4)
        ->and((float) $lines[0]->amount)->toBe(150000.0)
        ->and($lines[0]->reference)->toBe('VIR-2041')
        ->and((float) $lines[1]->amount)->toBe(-80000.0)
        ->and($lines[1]->line_date->toDateString())->toBe('2026-09-14');

    // Montant + sens + date à ± 3 jours (le chèque est daté de 2 jours après la transaction)
    expect($this->service->autoMatch($reconciliation))->toBe(3);

    $byLabel = $reconciliation->lines()->get()->keyBy('label');
    expect($byLabel['VIR SEPA YAO']->matched_transaction_id)->toBe($this->t1->id)
        ->and($byLabel['CHEQUE 118']->matched_transaction_id)->toBe($this->t2->id)
        ->and($byLabel['COMMISSIONS']->matched_transaction_id)->toBe($this->t3->id)
        ->and($byLabel['AGIOS']->status)->toBe(BankStatementLineStatus::Unmatched)
        ->and($this->t1->fresh()->is_reconciled)->toBeTrue()
        ->and($this->t1->fresh()->bank_reconciliation_id)->toBe($reconciliation->id)
        ->and($this->t4->fresh()->is_reconciled)->toBeFalse();

    $summary = $this->service->summary($reconciliation);
    expect($summary['reconciled_balance'])->toBe(545000.0)
        ->and($summary['is_balanced'])->toBeTrue()
        ->and($summary['lines_matched'])->toBe(3)
        ->and($summary['lines_unmatched'])->toBe(1);

    // Ligne sans objet écartée, puis clôture ; tout devient intangible
    $this->service->ignoreLine($byLabel['AGIOS']);
    $this->service->complete($reconciliation);

    $completed = $reconciliation->fresh();
    expect($completed->isCompleted())->toBeTrue()
        ->and($completed->completed_by)->toBe($this->owner->id)
        ->and($completed->completed_at)->not->toBeNull();

    expect(fn () => $this->t1->fresh()->unreconcile())->toThrow(TransactionException::class);
    expect(fn () => $this->service->mark($completed, [$this->t4->id]))->toThrow(BankReconciliationException::class);
    expect(fn () => $this->service->autoMatch($completed))->toThrow(BankReconciliationException::class);
    expect(fn () => $completed->delete())->toThrow(BankReconciliationException::class);
    expect(BankReconciliation::query()->count())->toBe(1);
});

it('pointe et dépointe manuellement, refuse la clôture avec écart et libère les transactions à la suppression du brouillon', function () {
    $reconciliation = $this->service->start($this->bank, '2026-09-30', 545000);

    expect($this->service->mark($reconciliation, [$this->t1->id, $this->t2->id]))->toBe(2)
        ->and($this->service->mark($reconciliation, [$this->t1->id]))->toBe(0)
        ->and($this->service->summary($reconciliation)['difference'])->toBe(-25000.0);

    expect(fn () => $this->service->complete($reconciliation))->toThrow(BankReconciliationException::class);

    $other = Account::factory()->cash()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'initial_balance' => 0, 'current_balance' => 0]);
    $foreign = $other->credit(1000, 'Autre caisse', TransactionCategory::Other);
    expect(fn () => $this->service->mark($reconciliation, [$foreign->id]))->toThrow(BankReconciliationException::class);

    expect($this->service->mark($reconciliation, [$this->t2->id], false))->toBe(1)
        ->and($this->t2->fresh()->is_reconciled)->toBeFalse()
        ->and($this->t2->fresh()->bank_reconciliation_id)->toBeNull();

    $reconciliation->delete();

    expect(BankReconciliation::query()->count())->toBe(0)
        ->and($this->t1->fresh()->is_reconciled)->toBeFalse()
        ->and($this->t1->fresh()->bank_reconciliation_id)->toBeNull();
});

it('la fiche pointe en masse, associe une ligne, clôture, et respecte le cloisonnement et le plan', function () {
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($this->organisation);

    $reconciliation = $this->service->start($this->bank, '2026-09-30', 545000);
    $line = $reconciliation->lines()->create(['line_date' => '2026-09-28', 'label' => 'COMMISSIONS', 'amount' => -25000, 'status' => BankStatementLineStatus::Unmatched]);

    Livewire::test(ViewBankReconciliation::class, ['record' => $reconciliation->getRouteKey()])
        ->assertOk()
        ->assertSee('Solde pointé')
        ->assertActionVisible('importStatement')
        ->assertActionVisible('autoMatch')
        ->assertActionVisible('complete');

    Livewire::test(TransactionsRelationManager::class, ['ownerRecord' => $reconciliation, 'pageClass' => ViewBankReconciliation::class])
        ->assertOk()
        ->assertCanSeeTableRecords([$this->t1, $this->t2, $this->t3])
        ->assertCanNotSeeTableRecords([$this->t4])
        ->callTableBulkAction('reconcileSelected', [$this->t1, $this->t2])
        ->assertHasNoTableBulkActionErrors();

    expect($this->t1->fresh()->bank_reconciliation_id)->toBe($reconciliation->id)
        ->and($this->t2->fresh()->is_reconciled)->toBeTrue();

    Livewire::test(StatementLinesRelationManager::class, ['ownerRecord' => $reconciliation, 'pageClass' => ViewBankReconciliation::class])
        ->assertOk()
        ->assertCanSeeTableRecords([$line])
        ->callTableAction('match', $line, data: ['transaction_id' => $this->t3->id])
        ->assertHasNoTableActionErrors();

    expect($line->fresh()->isMatched())->toBeTrue()
        ->and($this->t3->fresh()->is_reconciled)->toBeTrue();

    Livewire::test(ViewBankReconciliation::class, ['record' => $reconciliation->getRouteKey()])
        ->callAction('complete')
        ->assertHasNoActionErrors()
        ->assertNotified('Rapprochement clôturé');

    expect($reconciliation->fresh()->isCompleted())->toBeTrue();

    Livewire::test(TransactionsRelationManager::class, ['ownerRecord' => $reconciliation->fresh(), 'pageClass' => ViewBankReconciliation::class])
        ->assertTableBulkActionHidden('reconcileSelected')
        ->assertTableActionHidden('unreconcile', $this->t1);

    // Cloisonnement par organisation
    $neighbour = Organisation::factory()->create(['plan_id' => $this->plan->id]);
    expect(BankReconciliationResource::scopeEloquentQueryToTenant(BankReconciliation::query(), $neighbour)->count())->toBe(0)
        ->and(BankReconciliationResource::scopeEloquentQueryToTenant(BankReconciliation::query(), $this->organisation)->count())->toBe(1);

    // Sans la fonctionnalité du plan, la ressource disparaît
    $starter = Plan::factory()->create(['features' => [PlanFeature::Accounting->value]]);
    $this->organisation->update(['plan_id' => $starter->id]);
    Filament::setTenant($this->organisation->fresh());

    expect(BankReconciliationResource::canAccess())->toBeFalse()
        ->and(BankReconciliationResource::shouldRegisterNavigation())->toBeFalse();
});
