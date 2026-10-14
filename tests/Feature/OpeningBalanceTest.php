<?php

use App\Enums\FiscalYearStatus;
use App\Enums\JournalCode;
use App\Enums\PlanFeature;
use App\Exceptions\AccountingException;
use App\Filament\Farm\Resources\JournalEntries\Pages\ListJournalEntries;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\User;
use App\Services\AccountingService;
use Database\Seeders\ChartAccountSeeder;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * Bilan d'ouverture : reprise des soldes antérieurs à la tenue de la comptabilité dans GESTAVI.
 */
beforeEach(function () {
    $this->seed(ChartAccountSeeder::class);
    $this->seed(OrganisationPermissionSeeder::class);
    $plan = Plan::factory()->create(['features' => collect(PlanFeature::cases())->map->value->all()]);
    $this->organisation = Organisation::factory()->create(['plan_id' => $plan->id]);
    $this->owner = User::factory()->create();
    $this->organisation->addMember($this->owner, 'owner');

    $this->lines = [
        ['code' => '101', 'credit' => 5000000],
        ['code' => '242', 'debit' => 3000000],
        ['code' => '411', 'debit' => 2500000],
        ['code' => '401', 'credit' => 500000],
    ];
});

it('enregistre une seule fois un bilan d\'ouverture équilibré au journal AN, hors reconstruction', function () {
    $entry = app(AccountingService::class)->recordOpeningBalance($this->organisation->id, '2025-01-01', $this->lines, $this->owner->id);

    expect($entry->journal)->toBe(JournalCode::Opening)
        ->and($entry->entry_date->toDateString())->toBe('2025-01-01')
        ->and($entry->is_generated)->toBeFalse()
        ->and($entry->isBalanced())->toBeTrue()
        ->and((float) $entry->lines()->where('chart_account_code', '101')->sum('credit'))->toBe(5000000.0)
        ->and(app(AccountingService::class)->openingBalanceEntry($this->organisation->id)?->id)->toBe($entry->id);

    expect(fn () => app(AccountingService::class)->recordOpeningBalance($this->organisation->id, '2025-01-01', $this->lines))
        ->toThrow(AccountingException::class, 'déjà été saisi');

    app(AccountingService::class)->rebuild($this->organisation->id);

    expect(JournalEntry::query()->whereKey($entry->id)->exists())->toBeTrue();
});

it('refuse les comptes de trésorerie, de charges et de produits, le déséquilibre et un exercice clos', function () {
    $service = app(AccountingService::class);

    expect(fn () => $service->recordOpeningBalance($this->organisation->id, '2025-01-01', [['code' => '521', 'debit' => 100], ['code' => '101', 'credit' => 100]]))
        ->toThrow(AccountingException::class, 'compte 521');

    expect(fn () => $service->recordOpeningBalance($this->organisation->id, '2025-01-01', [['code' => '605', 'debit' => 100], ['code' => '101', 'credit' => 100]]))
        ->toThrow(AccountingException::class, 'compte 605');

    expect(fn () => $service->recordOpeningBalance($this->organisation->id, '2025-01-01', [['code' => '242', 'debit' => 100], ['code' => '101', 'credit' => 90]]))
        ->toThrow(AccountingException::class, 'déséquilibrée');

    FiscalYear::create([
        'organisation_id' => $this->organisation->id,
        'label' => '2024',
        'starts_on' => '2024-01-01',
        'ends_on' => '2024-12-31',
        'status' => FiscalYearStatus::Closed,
        'closed_at' => now(),
    ]);

    expect(fn () => $service->recordOpeningBalance($this->organisation->id, '2024-12-31', $this->lines))
        ->toThrow(AccountingException::class, 'clôturé');

    expect(JournalEntry::query()->where('organisation_id', $this->organisation->id)->count())->toBe(0);
});

it('le propriétaire saisit le bilan d\'ouverture depuis la liste des écritures, puis l\'action disparaît', function () {
    FiscalYear::create([
        'organisation_id' => $this->organisation->id,
        'label' => '2025',
        'starts_on' => '2025-01-01',
        'ends_on' => '2025-12-31',
        'status' => FiscalYearStatus::Open,
    ]);

    actingAs($this->owner);
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($this->organisation);

    // Clés de lignes déterministes : les lignes fournies remplacent celles par défaut au lieu de s'y ajouter
    \Filament\Forms\Components\Repeater::fake();

    Livewire::test(ListJournalEntries::class)
        ->assertActionVisible('openingBalance')
        ->callAction('openingBalance', ['date' => '2025-01-01', 'lines' => $this->lines])
        ->assertHasNoActionErrors()
        ->assertNotified('Bilan d\'ouverture enregistré');

    $entry = app(AccountingService::class)->openingBalanceEntry($this->organisation->id);

    expect($entry)->not->toBeNull()
        ->and($entry->fiscal_year_id)->not->toBeNull()
        ->and($entry->user_id)->toBe($this->owner->id);

    Livewire::test(ListJournalEntries::class)->assertActionHidden('openingBalance');
});
