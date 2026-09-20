<?php

use App\Enums\ExpenseCategory;
use App\Enums\SaleStatus;
use App\Filament\Farm\Pages\Reports\AccountingReportPage;
use App\Filament\Farm\Resources\FiscalYears\FiscalYearResource;
use App\Filament\Farm\Resources\JournalEntries\JournalEntryResource;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\JournalEntry;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\User;
use App\Services\SaleService;
use Database\Seeders\ChartAccountSeeder;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;

use function Pest\Laravel\actingAs;

/**
 * États comptables : balance, compte de résultat, export « journal Sage », ressources.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);
    $this->seed(ChartAccountSeeder::class);

    $plan = Plan::factory()->create(['features' => collect(\App\Enums\PlanFeature::cases())->map->value->all()]);
    $this->organisation = Organisation::factory()->create(['plan_id' => $plan->id]);
    $this->farm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);

    $this->owner = User::factory()->create();
    $this->organisation->addMember($this->owner, $this->organisation->organisationRoles()->where('slug', 'owner')->first());

    app(SaleService::class)->createSale(
        ['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'sale_date' => now()->toDateString(), 'status' => SaleStatus::Pending, 'tax_percent' => 18],
        [['product_name' => 'Poulets', 'quantity' => 100, 'unit_price' => 1000]],
    );
    Expense::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'category' => ExpenseCategory::Feed, 'amount' => 59000, 'tax_percent' => 18, 'tax_amount' => 9000, 'balance' => 59000, 'expense_date' => now()->toDateString()]);

    actingAs($this->owner);
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($this->organisation);
});

it('calcule la balance et le compte de résultat de la période', function () {
    $page = new AccountingReportPage;
    $page->mount();

    $balance = $page->getBalance()->keyBy('code');

    expect((float) $balance['411']['debit'])->toBe(118000.0)
        ->and((float) $balance['701']['credit'])->toBe(100000.0)
        ->and((float) $balance['4431']['credit'])->toBe(18000.0)
        ->and((float) $balance['6021']['debit'])->toBe(50000.0)
        ->and((float) $balance['4452']['debit'])->toBe(9000.0)
        ->and((float) $balance['401']['credit'])->toBe(59000.0)
        ->and(round($balance->sum('debit'), 2))->toBe(round($balance->sum('credit'), 2));

    $income = $page->getIncomeStatement();

    expect($income['total_produits'])->toBe(100000.0)
        ->and($income['total_charges'])->toBe(50000.0)
        ->and($income['resultat'])->toBe(50000.0);
});

it('l\'export Excel « journal Sage » contient les colonnes attendues et toutes les lignes', function () {
    $page = new AccountingReportPage;
    $page->mount();

    $content = $page->buildXlsx();
    $file = tempnam(sys_get_temp_dir(), 'journal-');
    file_put_contents($file, $content);

    $reader = new Reader;
    $reader->open($file);
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();
    @unlink($file);

    expect($rows[0])->toBe(['Date', 'Journal', 'N° pièce', 'Compte', 'Libellé', 'Débit', 'Crédit', 'Tiers'])
        ->and(count($rows) - 1)->toBe((int) \App\Models\JournalLine::query()->where('organisation_id', $this->organisation->id)->count())
        ->and(collect($rows)->skip(1)->pluck(1)->unique()->sort()->values()->all())->toBe(['AC', 'VE']);
});

it('les pages et ressources comptables s\'affichent pour un propriétaire', function () {
    expect(AccountingReportPage::canAccess())->toBeTrue()
        ->and(JournalEntryResource::canViewAny())->toBeTrue()
        ->and(JournalEntryResource::canCreate())->toBeFalse()
        ->and(JournalEntryResource::canEdit(JournalEntry::query()->first()))->toBeFalse()
        ->and(FiscalYearResource::canViewAny())->toBeTrue();

    Livewire::test(AccountingReportPage::class)
        ->assertOk()
        ->assertSee('Compte de résultat simplifié')
        ->assertSee('Balance des comptes');

    Livewire::test(\App\Filament\Farm\Resources\JournalEntries\Pages\ListJournalEntries::class)
        ->assertOk()
        ->assertCanSeeTableRecords(JournalEntry::query()->where('organisation_id', $this->organisation->id)->get());
});
