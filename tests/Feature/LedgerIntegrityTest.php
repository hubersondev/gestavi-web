<?php

use App\Enums\ExpenseCategory;
use App\Enums\FiscalYearStatus;
use App\Enums\LedgerAnchorReason;
use App\Enums\TransactionCategory;
use App\Exceptions\AccountingException;
use App\Models\Account;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAnchor;
use App\Models\Organisation;
use App\Models\Transaction;
use App\Services\AccountingService;
use App\Services\LedgerService;
use Database\Seeders\ChartAccountSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Ledger intangible : triggers append-only, chaîne de hachage, ancrage.
 */
beforeEach(function () {
    $this->seed(ChartAccountSeeder::class);
    $this->organisation = Organisation::factory()->create();
    $this->farm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);
    $this->cash = Account::factory()->cash()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'initial_balance' => 0, 'current_balance' => 0, 'is_active' => true]);
    $this->ledger = app(LedgerService::class);
});

/**
 * Chaque tentative interdite tourne dans un savepoint : la transaction du test reste utilisable après le rejet.
 */
function expectLedgerRejects(callable $attempt): void
{
    expect(fn () => DB::transaction($attempt))->toThrow(QueryException::class, 'Journal intangible');
}

function postCashMovements(Account $cash, int $count): void
{
    for ($i = 1; $i <= $count; $i++) {
        $cash->credit(1000 * $i, "Encaissement {$i}", TransactionCategory::Other);
    }
}

function entriesOf(Organisation $organisation)
{
    return JournalEntry::query()->where('organisation_id', $organisation->id)->orderBy('id')->get();
}

it('refuse toute modification ou suppression directe d\'une écriture ou d\'une ligne', function () {
    postCashMovements($this->cash, 1);
    $entry = entriesOf($this->organisation)->first();

    expectLedgerRejects(fn () => DB::table('journal_entries')->where('id', $entry->id)->update(['label' => 'falsifié']));

    expectLedgerRejects(fn () => DB::table('journal_lines')->where('journal_entry_id', $entry->id)->update(['debit' => 1]));

    expectLedgerRejects(fn () => DB::table('journal_lines')->where('journal_entry_id', $entry->id)->delete());

    expectLedgerRejects(fn () => DB::table('journal_entries')->where('id', $entry->id)->delete());

    expect(JournalEntry::query()->whereKey($entry->id)->exists())->toBeTrue()
        ->and($entry->fresh()->label)->not->toBe('falsifié');
});

it('protège le journal de trésorerie mais laisse passer le rapprochement bancaire', function () {
    $transaction = $this->cash->credit(5000, 'Vente comptant', TransactionCategory::Other);

    expectLedgerRejects(fn () => Transaction::query()->whereKey($transaction->id)->update(['amount' => 1]));

    expectLedgerRejects(fn () => Transaction::query()->whereKey($transaction->id)->delete());

    $transaction->update(['is_reconciled' => true, 'reconciled_at' => now()]);

    expect($transaction->fresh()->is_reconciled)->toBeTrue()
        ->and((float) $transaction->fresh()->amount)->toBe(5000.0);
});

it('chaîne chaque écriture à la précédente et la chaîne se vérifie', function () {
    postCashMovements($this->cash, 3);
    $entries = entriesOf($this->organisation);

    expect($entries)->toHaveCount(3)
        ->and($entries[0]->previous_hash)->toBeNull()
        ->and($entries[0]->hash)->toHaveLength(64)
        ->and($entries[1]->previous_hash)->toBe($entries[0]->hash)
        ->and($entries[2]->previous_hash)->toBe($entries[1]->hash)
        ->and($entries[2]->hash)->toBe($this->ledger->expectedHash($entries[2], $entries[1]->hash));

    $result = $this->ledger->verify($this->organisation->id);

    expect($result['valid'])->toBeTrue()
        ->and($result['checked'])->toBe(3)
        ->and($result['head_hash'])->toBe($entries[2]->hash);
});

it('détecte une ligne altérée et une écriture supprimée même avec le verrou levé', function () {
    postCashMovements($this->cash, 3);
    $entries = entriesOf($this->organisation);

    $this->ledger->withUnlockedLedger(function () use ($entries): void {
        JournalLine::query()->where('journal_entry_id', $entries[1]->id)->where('debit', '>', 0)->update(['debit' => 999999]);
    });

    $tampered = $this->ledger->verify($this->organisation->id);

    expect($tampered['valid'])->toBeFalse()
        ->and($tampered['broken_entry_id'])->toBe($entries[1]->id)
        ->and($tampered['reason'])->toBe('hash_mismatch')
        ->and($tampered['checked'])->toBe(1);

    $this->ledger->withUnlockedLedger(function () use ($entries): void {
        JournalLine::query()->where('journal_entry_id', $entries[1]->id)->delete();
        JournalEntry::query()->whereKey($entries[1]->id)->delete();
    });

    $broken = $this->ledger->verify($this->organisation->id);

    expect($broken['valid'])->toBeFalse()
        ->and($broken['broken_entry_id'])->toBe($entries[2]->id)
        ->and($broken['reason'])->toBe('chain_break');
});

it('une extourne prolonge la chaîne sans la rompre', function () {
    $expense = Expense::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'amount' => 118000, 'tax_percent' => 18, 'tax_amount' => 18000, 'category' => ExpenseCategory::Feed]);
    $entry = JournalEntry::query()->where('reference_type', Expense::class)->where('reference_id', $expense->id)->first();

    $reversal = app(AccountingService::class)->reverse($entry, 'Test extourne');

    expect($reversal->previous_hash)->toBe(entriesOf($this->organisation)->reverse()->values()[1]->hash)
        ->and($this->ledger->verify($this->organisation->id)['valid'])->toBeTrue();
});

it('la clôture d\'un exercice ancre la tête de chaîne en base et hors base', function () {
    Storage::fake('local');
    config()->set('ledger.anchor_disk', 'local');

    postCashMovements($this->cash, 2);

    $fiscalYear = FiscalYear::query()->create([
        'organisation_id' => $this->organisation->id,
        'label' => 'Exercice '.now()->year,
        'starts_on' => now()->startOfYear()->toDateString(),
        'ends_on' => now()->endOfYear()->toDateString(),
        'status' => FiscalYearStatus::Open,
    ]);

    app(AccountingService::class)->closeFiscalYear($fiscalYear);

    $anchor = LedgerAnchor::query()->where('organisation_id', $this->organisation->id)->latest('id')->first();
    $head = entriesOf($this->organisation)->last();

    expect($anchor)->not->toBeNull()
        ->and($anchor->reason)->toBe(LedgerAnchorReason::FiscalYearClose)
        ->and($anchor->is_valid)->toBeTrue()
        ->and($anchor->head_entry_id)->toBe($head->id)
        ->and($anchor->head_hash)->toBe($head->hash)
        ->and($anchor->entry_count)->toBe(entriesOf($this->organisation)->count());

    $file = 'ledger/'.$this->organisation->id.'-'.$this->organisation->slug.'.jsonl';
    Storage::disk('local')->assertExists($file);

    $lines = array_values(array_filter(explode("\n", Storage::disk('local')->get($file))));
    $decoded = json_decode(end($lines), true);

    expect($decoded['head_hash'])->toBe($head->hash)
        ->and($decoded['reason'])->toBe('fiscal_year_close');

    expectLedgerRejects(fn () => LedgerAnchor::query()->whereKey($anchor->id)->update(['head_hash' => str_repeat('0', 64)]));
});

it('un ancrage sur chaîne rompue est tracé comme invalide puis signalé', function () {
    Storage::fake('local');
    postCashMovements($this->cash, 2);
    $entries = entriesOf($this->organisation);

    $this->ledger->withUnlockedLedger(function () use ($entries): void {
        JournalEntry::query()->whereKey($entries[1]->id)->update(['label' => 'falsifié']);
    });

    expect(fn () => $this->ledger->anchor($this->organisation->id, LedgerAnchorReason::Manual))
        ->toThrow(AccountingException::class, 'Intégrité du ledger compromise');

    $anchor = LedgerAnchor::query()->where('organisation_id', $this->organisation->id)->latest('id')->first();

    expect($anchor->is_valid)->toBeFalse()
        ->and($anchor->notes)->toContain($entries[1]->entry_number);
});

it('la reconstruction fonctionne avec les clés en RESTRICT et laisse une chaîne intègre ancrée', function () {
    Storage::fake('local');
    postCashMovements($this->cash, 3);
    $before = entriesOf($this->organisation)->count();

    $counts = app(AccountingService::class)->rebuild($this->organisation->id);

    expect($counts['transactions'])->toBe(3)
        ->and(entriesOf($this->organisation)->count())->toBe($before)
        ->and($this->ledger->verify($this->organisation->id)['valid'])->toBeTrue()
        ->and(LedgerAnchor::query()->where('organisation_id', $this->organisation->id)->where('reason', LedgerAnchorReason::Rebuild->value)->exists())->toBeTrue();
});

it('la commande de vérification échoue sur une chaîne rompue et réussit sinon', function () {
    postCashMovements($this->cash, 2);

    $this->artisan('gestavi:ledger-verify', ['--organisation' => $this->organisation->id])
        ->expectsOutputToContain('intègres')
        ->assertSuccessful();

    $this->ledger->withUnlockedLedger(function (): void {
        JournalLine::query()->where('organisation_id', $this->organisation->id)->where('credit', '>', 0)->limit(1)->update(['credit' => 1]);
    });

    $this->artisan('gestavi:ledger-verify', ['--organisation' => $this->organisation->id])
        ->expectsOutputToContain('rompue')
        ->assertFailed();
});

it('la commande d\'ancrage ancre chaque organisation ayant des écritures', function () {
    Storage::fake('local');
    postCashMovements($this->cash, 1);

    $this->artisan('gestavi:ledger-anchor')->assertSuccessful();

    expect(LedgerAnchor::query()->where('organisation_id', $this->organisation->id)->where('reason', LedgerAnchorReason::Scheduled->value)->count())->toBe(1);
});
