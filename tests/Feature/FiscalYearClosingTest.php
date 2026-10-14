<?php

use App\Enums\ExpenseCategory;
use App\Enums\FiscalYearStatus;
use App\Enums\JournalCode;
use App\Enums\SaleStatus;
use App\Exceptions\AccountingException;
use App\Models\Account;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Organisation;
use App\Services\AccountingService;
use App\Services\SaleService;
use Database\Seeders\ChartAccountSeeder;

/**
 * Exercices comptables : verrouillage des périodes clôturées et détermination du résultat.
 */
beforeEach(function () {
    $this->seed(ChartAccountSeeder::class);
    $this->organisation = Organisation::factory()->create();
    $this->farm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);

    $this->fiscalYear = FiscalYear::create([
        'organisation_id' => $this->organisation->id,
        'label' => 'N-1',
        'starts_on' => now()->subYear()->startOfYear()->toDateString(),
        'ends_on' => now()->subYear()->endOfYear()->toDateString(),
        'status' => FiscalYearStatus::Open,
    ]);
});

it('la clôture solde les classes 6 et 7 vers 131 et verrouille la période', function () {
    $lastYear = now()->subYear()->setMonth(6)->setDay(15);

    // Produit : facture 100 000 HT (TVA 0) ; charge : dépense 40 000
    app(SaleService::class)->createSale(
        ['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'sale_date' => $lastYear->toDateString(), 'status' => SaleStatus::Pending, 'tax_percent' => 0],
        [['product_name' => 'Œufs', 'quantity' => 1000, 'unit_price' => 100]],
    );
    Expense::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'category' => ExpenseCategory::Utilities, 'amount' => 40000, 'balance' => 40000, 'expense_date' => $lastYear->toDateString()]);

    expect(JournalEntry::query()->where('fiscal_year_id', $this->fiscalYear->id)->count())->toBe(2);

    $closed = app(AccountingService::class)->closeFiscalYear($this->fiscalYear);

    expect($closed->isClosed())->toBeTrue()
        ->and((float) $closed->net_result)->toBe(60000.0);

    $closing = JournalEntry::query()->where('reference_type', FiscalYear::class)->where('reference_id', $this->fiscalYear->id)->where('journal', JournalCode::Miscellaneous->value)->first();

    expect($closing)->not->toBeNull()
        ->and($closing->isBalanced())->toBeTrue()
        ->and((float) $closing->lines()->where('chart_account_code', '701')->sum('debit'))->toBe(100000.0)
        ->and((float) $closing->lines()->where('chart_account_code', '605')->sum('credit'))->toBe(40000.0)
        ->and((float) $closing->lines()->where('chart_account_code', '131')->sum('credit'))->toBe(60000.0);

    // À-nouveaux : les soldes de bilan (créance client, dette fournisseur, résultat) sont reportés au 1er jour suivant
    $opening = JournalEntry::query()->where('reference_type', FiscalYear::class)->where('reference_id', $this->fiscalYear->id)->where('journal', JournalCode::Opening->value)->first();

    expect($opening)->not->toBeNull()
        ->and($opening->entry_date->toDateString())->toBe(now()->subYear()->endOfYear()->addDay()->toDateString())
        ->and($opening->isBalanced())->toBeTrue()
        ->and($opening->lines()->whereRaw("LEFT(chart_account_code, 1) IN ('6', '7')")->exists())->toBeFalse()
        ->and((float) $opening->lines()->where('chart_account_code', '411')->sum('debit'))->toBe(100000.0)
        ->and((float) $opening->lines()->where('chart_account_code', '401')->sum('credit'))->toBe(40000.0)
        ->and((float) $opening->lines()->where('chart_account_code', '121')->sum('credit'))->toBe(60000.0)
        ->and($opening->lines()->where('chart_account_code', '131')->exists())->toBeFalse();

    // Toute transaction ou écriture datée dans l'exercice clos est refusée
    $cash = Account::factory()->cash()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'initial_balance' => 0, 'current_balance' => 0, 'is_active' => true]);

    expect(fn () => $cash->credit(1000, 'Rétro-saisie interdite', date: $lastYear->toDateString()))
        ->toThrow(AccountingException::class);

    expect(fn () => app(AccountingService::class)->record(
        ['organisation_id' => $this->organisation->id, 'entry_date' => $lastYear, 'journal' => \App\Enums\JournalCode::Miscellaneous, 'label' => 'OD'],
        [['code' => '571', 'debit' => 100], ['code' => '471', 'credit' => 100]],
    ))->toThrow(AccountingException::class);

    // Une écriture datée après la clôture reste possible
    $cash->credit(1000, 'Saisie courante');
    expect($cash->fresh()->current_balance)->toEqual(1000);

    expect(fn () => app(AccountingService::class)->closeFiscalYear($this->fiscalYear->fresh()))->toThrow(AccountingException::class);
});

it('un exercice créé ou redaté après coup rattache les écritures de sa période et détache les autres', function () {
    $twoYearsAgo = now()->subYears(2)->setMonth(3)->setDay(10);

    app(SaleService::class)->createSale(
        ['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'sale_date' => $twoYearsAgo->toDateString(), 'status' => SaleStatus::Pending, 'tax_percent' => 0],
        [['product_name' => 'Œufs', 'quantity' => 10, 'unit_price' => 100]],
    );
    $entry = JournalEntry::query()->where('organisation_id', $this->organisation->id)->sole();
    expect($entry->fiscal_year_id)->toBeNull();

    // Création après coup : l'écriture est rattachée, celles d'une autre organisation ne le sont pas
    $other = Organisation::factory()->create();
    $otherFarm = Farm::factory()->create(['organisation_id' => $other->id]);
    Expense::factory()->create(['organisation_id' => $other->id, 'farm_id' => $otherFarm->id, 'category' => ExpenseCategory::Utilities, 'amount' => 500, 'balance' => 500, 'expense_date' => $twoYearsAgo->toDateString()]);

    $previous = FiscalYear::create([
        'organisation_id' => $this->organisation->id,
        'label' => 'N-2',
        'starts_on' => $twoYearsAgo->copy()->startOfYear()->toDateString(),
        'ends_on' => $twoYearsAgo->copy()->endOfYear()->toDateString(),
        'status' => FiscalYearStatus::Open,
    ]);

    expect($entry->fresh()->fiscal_year_id)->toBe($previous->id)
        ->and($previous->entries()->count())->toBe(1)
        ->and(JournalEntry::query()->where('organisation_id', $other->id)->sole()->fiscal_year_id)->toBeNull();

    // Redatage excluant l'écriture : elle est détachée ; le contenu comptable reste intangible
    $previous->update(['starts_on' => $twoYearsAgo->copy()->setMonth(6)->startOfMonth()->toDateString()]);

    expect($entry->fresh()->fiscal_year_id)->toBeNull()
        ->and((string) $entry->fresh()->hash)->toBe((string) $entry->hash);

    expect(fn () => JournalEntry::query()->whereKey($entry->id)->update(['label' => 'Altération']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('les à-nouveaux s\'enchaînent d\'un exercice à l\'autre sans cumuler les soldes, et survivent à la reconstruction', function () {
    $lastYear = now()->subYear()->setMonth(6)->setDay(15);

    app(SaleService::class)->createSale(
        ['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'sale_date' => $lastYear->toDateString(), 'status' => SaleStatus::Pending, 'tax_percent' => 0],
        [['product_name' => 'Œufs', 'quantity' => 1000, 'unit_price' => 100]],
    );
    Expense::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'category' => ExpenseCategory::Utilities, 'amount' => 40000, 'balance' => 40000, 'expense_date' => $lastYear->toDateString()]);

    $currentYear = FiscalYear::create([
        'organisation_id' => $this->organisation->id,
        'label' => 'N',
        'starts_on' => now()->startOfYear()->toDateString(),
        'ends_on' => now()->endOfYear()->toDateString(),
        'status' => FiscalYearStatus::Open,
    ]);

    app(AccountingService::class)->closeFiscalYear($this->fiscalYear);

    // L'écriture d'à-nouveaux est rattachée à l'exercice qu'elle ouvre
    $openingN = JournalEntry::query()->where('journal', JournalCode::Opening->value)->where('reference_id', $this->fiscalYear->id)->first();
    expect($openingN->fiscal_year_id)->toBe($currentYear->id);

    // Exercice N sans mouvement : les à-nouveaux de N+1 reprennent les mêmes soldes, sans doublon, et le résultat est nul
    $closedN = app(AccountingService::class)->closeFiscalYear($currentYear);
    $openingNext = JournalEntry::query()->where('journal', JournalCode::Opening->value)->where('reference_id', $currentYear->id)->first();

    expect((float) $closedN->net_result)->toBe(0.0)
        ->and($openingNext)->not->toBeNull()
        ->and($openingNext->entry_date->toDateString())->toBe(now()->endOfYear()->addDay()->toDateString())
        ->and($openingNext->isBalanced())->toBeTrue()
        ->and((float) $openingNext->lines()->where('chart_account_code', '411')->sum('debit'))->toBe(100000.0)
        ->and((float) $openingNext->lines()->where('chart_account_code', '401')->sum('credit'))->toBe(40000.0)
        ->and((float) $openingNext->lines()->where('chart_account_code', '121')->sum('credit'))->toBe(60000.0);

    // La reconstruction rejoue les faits (même dans l'exercice clos) et conserve la clôture de N-1 et les deux à-nouveaux
    app(AccountingService::class)->rebuild($this->organisation->id);

    expect(JournalEntry::query()->where('journal', JournalCode::Opening->value)->count())->toBe(2)
        ->and(JournalEntry::query()->where('reference_type', FiscalYear::class)->where('journal', JournalCode::Miscellaneous->value)->count())->toBe(1)
        ->and(JournalEntry::query()->where('journal', JournalCode::Sales->value)->where('fiscal_year_id', $this->fiscalYear->id)->count())->toBe(1);
});
