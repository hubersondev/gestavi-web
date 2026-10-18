<?php

use App\Enums\BankStatementLineStatus;
use App\Enums\PlanFeature;
use App\Enums\TransactionCategory;
use App\Exceptions\BankReconciliationException;
use App\Filament\Farm\Resources\BankReconciliations\Pages\ViewBankReconciliation;
use App\Filament\Farm\Resources\BankReconciliations\RelationManagers\StatementLinesRelationManager;
use App\Filament\Farm\Resources\Transactions\Pages\ViewTransaction;
use App\Models\Account;
use App\Models\BankStatementLine;
use App\Models\Farm;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\User;
use App\Services\AccountService;
use App\Services\BankReconciliationService;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * Rapprochement bancaire, correctifs de la revue : envoi de fichier sûr, CSV reconnu au contenu,
 * relevés successifs, solde figé à la clôture, pointages hors rapprochement, centimes, compte
 * archivé, lecture des relevés français.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);

    $plan = Plan::factory()->create(['features' => collect(PlanFeature::cases())->map->value->all()]);
    $this->organisation = Organisation::factory()->create(['plan_id' => $plan->id]);
    $this->owner = User::factory()->create();
    $this->organisation->addMember($this->owner, 'owner');
    $this->farm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);

    actingAs($this->owner);
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($this->organisation);

    $this->bank = Account::factory()->bank()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'initial_balance' => 500000, 'current_balance' => 500000]);
    $this->service = app(BankReconciliationService::class);
});

function writeStatement(string $content, string $extension = 'txt'): string
{
    $path = tempnam(sys_get_temp_dir(), 'releve').'.'.$extension;
    file_put_contents($path, $content);

    return $path;
}

it('refuse un chemin de fichier forgé à l\'import du relevé et ne supprime pas le fichier visé', function () {
    Storage::fake('local');
    Storage::disk('local')->put('expenses/attachments/justificatif.pdf', 'pièce comptable');

    $reconciliation = $this->service->start($this->bank, '2026-09-30', 500000);

    Livewire::test(ViewBankReconciliation::class, ['record' => $reconciliation->getRouteKey()])
        ->callAction('importStatement', data: ['file' => ['forge' => 'expenses/attachments/justificatif.pdf']])
        ->assertHasActionErrors(['file']);

    expect(Storage::disk('local')->exists('expenses/attachments/justificatif.pdf'))->toBeTrue()
        ->and($reconciliation->lines()->count())->toBe(0);
});

it('importe depuis l\'écran un CSV point-virgule envoyé par le navigateur, puis efface le fichier', function () {
    Storage::fake('local');
    $this->bank->credit(150000, 'Virement client', TransactionCategory::Payment, date: '2026-09-05');
    $reconciliation = $this->service->start($this->bank, '2026-09-30', 650000);

    $csv = "Date;Libellé;Débit;Crédit;Référence\n05/09/2026;VIR CLIENT;;150 000;VIR-1\n";

    Livewire::test(ViewBankReconciliation::class, ['record' => $reconciliation->getRouteKey()])
        ->callAction('importStatement', data: ['file' => UploadedFile::fake()->createWithContent('releve.csv', $csv)])
        ->assertHasNoActionErrors()
        ->assertNotified('Relevé importé');

    expect($reconciliation->lines()->sole()->amount)->toEqual('150000.00')
        ->and(Storage::disk('local')->allFiles('imports'))->toBe([]);
});

it('lit les relevés français : préambule, colonne Valeur, Windows-1252, parenthèses, dates sans zéro, texte refusé', function () {
    $reconciliation = $this->service->start($this->bank, '2026-09-30', 500000);

    $csv = mb_convert_encoding(implode("\r\n", [
        'RELEVE DE COMPTE N° 0042;;;;',
        'Période du 01/09/2026 au 30/09/2026;;;;',
        '',
        'Date;Libellé;Valeur;Débit;Crédit',
        '12/09/2026;CHQ 118;14/09/2026;80 000;',
        '5/9/2026;PRÉLÈVEMENT CIE;6/9/2026;(25 000) XOF;',
        '10/09/2026;RETRAIT DAB;10/09/2026;50 000;',
        '10/09/2026;RETRAIT DAB;10/09/2026;50 000;',
        '31/02/2026;DATE IMPOSSIBLE;;1 000;',
        'A;TEXTE;;1 000;',
    ]), 'Windows-1252', 'UTF-8');
    $path = writeStatement($csv, 'csv');

    try {
        $result = $this->service->importStatement($reconciliation, $path);
    } finally {
        @unlink($path);
    }

    $lines = $reconciliation->lines()->orderBy('line_date')->orderBy('id')->get();

    expect($result['imported'])->toBe(4)
        ->and($result['errors'])->toHaveCount(2)
        ->and($lines->pluck('amount')->map(fn ($a) => (float) $a)->all())->toBe([-25000.0, -50000.0, -50000.0, -80000.0])
        ->and($lines[0]->line_date->toDateString())->toBe('2026-09-05')
        ->and($lines[0]->label)->toBe('PRÉLÈVEMENT CIE');

    expect(BankReconciliationService::parseAmount('−25 000'))->toBe(-25000.0)
        ->and(BankReconciliationService::parseAmount('25 000 DB'))->toBe(-25000.0)
        ->and(BankReconciliationService::parseAmount('1.250.000'))->toBe(1250000.0)
        ->and(BankReconciliationService::parseDate('2026-02-31'))->toBeNull();
});

it('fige le solde d\'un rapprochement clôturé et enchaîne les relevés dans l\'ordre', function () {
    $sale = $this->bank->credit(100000, 'Vente', TransactionCategory::Sale, date: '2026-09-10');
    $cheque = $this->bank->debit(10000, 'Chèque fournisseur', TransactionCategory::Expense, date: '2026-09-29');

    // Septembre : le chèque n'est pas encore débité par la banque
    $september = $this->service->start($this->bank, '2026-09-30', 600000);
    $this->service->mark($september, [$sale->id]);
    $this->service->complete($september);

    expect(fn () => $this->service->start($this->bank, '2026-09-15', 590000))->toThrow(BankReconciliationException::class);

    $october = $this->service->start($this->bank, '2026-10-31', 590000);
    $this->service->mark($october, [$cheque->id]);
    $this->service->complete($october);

    $summary = $this->service->summary($september->fresh());
    expect($summary['reconciled_balance'])->toBe(600000.0)
        ->and($summary['is_balanced'])->toBeTrue()
        ->and((float) $september->fresh()->reconciled_balance)->toBe(600000.0);

    // Une transaction postérieure au relevé ne s'y pointe pas
    $november = $this->service->start($this->bank, '2026-11-30', 590000);
    $late = $this->bank->credit(5000, 'Décembre', TransactionCategory::Sale, date: '2026-12-02');
    expect(fn () => $this->service->mark($november, [$late->id]))->toThrow(BankReconciliationException::class);
});

it('compte et rattache à la clôture les pointages hors rapprochement, puis les verrouille', function () {
    $sale = $this->bank->credit(150000, 'Vente', TransactionCategory::Sale, date: '2026-09-05');
    $sale->reconcile();

    $reconciliation = $this->service->start($this->bank, '2026-09-30', 650000);

    expect($reconciliation->candidateTransactions()->pluck('id')->all())->toContain($sale->id)
        ->and($this->service->summary($reconciliation)['is_balanced'])->toBeTrue();

    $this->service->complete($reconciliation);

    expect($sale->fresh()->bank_reconciliation_id)->toBe($reconciliation->id);

    // Le bouton d'annulation de la fiche transaction disparaît pour un pointage par relevé
    Livewire::test(ViewTransaction::class, ['record' => $sale->getRouteKey()])
        ->assertActionHidden('unreconcile');
});

it('dépointer une transaction libère la ligne du relevé qui la portait', function () {
    $sale = $this->bank->credit(150000, 'Vente', TransactionCategory::Sale, date: '2026-09-05');
    $reconciliation = $this->service->start($this->bank, '2026-09-30', 650000);
    $line = BankStatementLine::factory()->create(['bank_reconciliation_id' => $reconciliation->id, 'line_date' => '2026-09-05', 'amount' => 150000, 'reference' => null]);

    $this->service->autoMatch($reconciliation);
    expect($line->fresh()->isMatched())->toBeTrue();

    $this->service->mark($reconciliation, [$sale->id], false);

    expect($line->fresh()->status)->toBe(BankStatementLineStatus::Unmatched)
        ->and($line->fresh()->matched_transaction_id)->toBeNull();

    // Deux lignes ne portent jamais la même transaction
    $this->service->matchLine($line->fresh(), $sale->fresh());
    $twin = BankStatementLine::factory()->create(['bank_reconciliation_id' => $reconciliation->id, 'line_date' => '2026-09-05', 'amount' => 150000]);
    expect(fn () => $this->service->matchLine($twin, $sale->fresh()))->toThrow(BankReconciliationException::class);

    // La suppression des lignes est proposée sur la fiche
    Livewire::test(StatementLinesRelationManager::class, ['ownerRecord' => $reconciliation, 'pageClass' => ViewBankReconciliation::class])
        ->assertTableBulkActionVisible('delete');
});

it('rapproche à moins d\'un franc près les montants avec centimes (TVA) et clôture', function () {
    $invoice = $this->bank->credit(14567.10, 'Facture TVA 18 %', TransactionCategory::Payment, date: '2026-09-12');
    $reconciliation = $this->service->start($this->bank, '2026-09-30', 514567);
    BankStatementLine::factory()->create(['bank_reconciliation_id' => $reconciliation->id, 'line_date' => '2026-09-13', 'amount' => 14567, 'reference' => null]);

    expect($this->service->autoMatch($reconciliation))->toBe(1)
        ->and($invoice->fresh()->is_reconciled)->toBeTrue()
        ->and($this->service->summary($reconciliation)['is_balanced'])->toBeTrue();

    $this->service->complete($reconciliation);

    expect($reconciliation->fresh()->isCompleted())->toBeTrue()
        ->and(BankReconciliationService::money(0.1))->toBe('0,10 XOF');
});

it('pointe d\'office la transaction d\'ouverture et reste consultable quand le compte est archivé', function () {
    $account = app(AccountService::class)->createAccount([
        'organisation_id' => $this->organisation->id,
        'farm_id' => $this->farm->id,
        'name' => 'Banque NSIA',
        'type' => 'bank',
        'initial_balance' => 200000,
        'initial_balance_date' => '2026-01-01',
    ]);

    $reconciliation = $this->service->start($account, '2026-09-30', 200000);

    expect($this->service->summary($reconciliation)['is_balanced'])->toBeTrue();

    $this->service->complete($reconciliation);
    $account->delete();

    Livewire::test(ViewBankReconciliation::class, ['record' => $reconciliation->getRouteKey()])
        ->assertOk()
        ->assertSee('Banque NSIA');
});

it('ajoute au plan Pro existant les fonctionnalités introduites depuis sa création', function () {
    $pro = Plan::factory()->create(['slug' => 'pro', 'features' => [PlanFeature::Accounting->value]]);

    $migration = require database_path('migrations/2026_10_18_090000_harden_bank_reconciliations.php');
    (new ReflectionMethod($migration, 'addProFeatures'))->invoke($migration);

    expect($pro->fresh()->features)->toContain('bank_reconciliation', 'weather_integration', 'multi_farm_comparison', 'ai_predictions', 'accounting');
});
