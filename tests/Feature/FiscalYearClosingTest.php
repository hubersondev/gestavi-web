<?php

use App\Enums\ExpenseCategory;
use App\Enums\FiscalYearStatus;
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

    $closing = JournalEntry::query()->where('reference_type', FiscalYear::class)->where('reference_id', $this->fiscalYear->id)->first();

    expect($closing)->not->toBeNull()
        ->and($closing->isBalanced())->toBeTrue()
        ->and((float) $closing->lines()->where('chart_account_code', '701')->sum('debit'))->toBe(100000.0)
        ->and((float) $closing->lines()->where('chart_account_code', '605')->sum('credit'))->toBe(40000.0)
        ->and((float) $closing->lines()->where('chart_account_code', '131')->sum('credit'))->toBe(60000.0);

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
