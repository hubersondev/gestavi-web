<?php

use App\Enums\AccountType;
use App\Enums\ExpenseStatus;
use App\Enums\PlanFeature;
use App\Enums\SaleStatus;
use App\Filament\Farm\Pages\DataImport;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AccountService;
use App\Services\Imports\ExcelImportService;
use App\Services\Imports\SpreadsheetReader;
use Database\Seeders\ChartAccountSeeder;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

use function Pest\Laravel\actingAs;

/**
 * Import Excel : modèles, contrôle complet avant écriture, import transactionnel des référentiels et des pièces.
 */
beforeEach(function () {
    $this->seed(ChartAccountSeeder::class);
    $this->seed(OrganisationPermissionSeeder::class);
    $plan = Plan::factory()->create(['features' => collect(PlanFeature::cases())->map->value->all()]);
    $this->organisation = Organisation::factory()->create(['plan_id' => $plan->id]);
    $this->farm = Farm::factory()->create(['organisation_id' => $this->organisation->id, 'name' => 'Ferme Anyama']);
    $this->owner = User::factory()->create();
    $this->organisation->addMember($this->owner, 'owner');
    $this->service = app(ExcelImportService::class);
    // L'import se fait toujours par un utilisateur connecté : les journaux (embauche…) portent son identifiant
    actingAs($this->owner);
});

/**
 * Écrit un classeur Excel temporaire à partir d'en-têtes libellés et de lignes.
 */
function writeWorkbook(array $headers, array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'gestavi-import-').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues($headers));

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();

    return $path;
}

it('génère un modèle lisible par le lecteur, avec les en-têtes normalisés et une feuille d\'aide', function () {
    $path = $this->service->writeTemplate('customers');

    $read = (new SpreadsheetReader)->read($path);

    expect($read['headers'])->toContain('nom', 'type', 'telephone', 'plafond_credit')
        ->and($read['rows'])->toHaveCount(2)
        ->and($read['rows'][0]['values']['nom'])->toBe('Maquis Chez Tantie');
});

it('importe les référentiels : clients, fournisseurs, produits, stocks et employés', function () {
    $customers = writeWorkbook(['Nom', 'Type', 'Téléphone', 'Plafond crédit'], [
        ['Maquis Chez Tantie', 'restaurant', '0708091011', 150000],
        ['Koffi Aya', '', '0102030405', ''],
    ]);
    $result = $this->service->import('customers', $customers, $this->organisation, $this->owner->id);

    expect($result['errors'])->toBe([])
        ->and($result['imported'])->toBe(2)
        ->and(Customer::query()->where('organisation_id', $this->organisation->id)->count())->toBe(2)
        ->and(Customer::query()->where('name', 'Koffi Aya')->sole()->type->value)->toBe('particulier');

    $suppliers = writeWorkbook(['Nom', 'Téléphone', 'Ferme'], [['FACI', '2722000000', ''], ['Véto du Nord', '', 'Ferme Anyama']]);
    expect($this->service->import('suppliers', $suppliers, $this->organisation)['imported'])->toBe(2)
        ->and(Supplier::query()->where('name', 'Véto du Nord')->sole()->farm_id)->toBe($this->farm->id);

    $products = writeWorkbook(['Nom', 'Code', 'Type', 'Unité', 'Prix unitaire'], [['Plateau de 12 œufs', 'OEUF-P12', 'plateau_oeufs', 'plateau', 1300]]);
    $result = $this->service->import('products', $products, $this->organisation);
    expect($result['errors'])->toBe([])->and($result['imported'])->toBe(1)
        ->and(Product::query()->where('code', 'OEUF-P12')->sole()->kind->value)->toBe('eggs_tray');

    $stocks = writeWorkbook(['Nom', 'Catégorie', 'Type de catégorie', 'Unité', 'Quantité', 'Prix unitaire'], [['Aliment démarrage', 'Aliment', 'aliment', 'sac', 40, 18500]]);
    expect($this->service->import('stocks', $stocks, $this->organisation)['imported'])->toBe(1)
        ->and((float) Stock::query()->where('name', 'Aliment démarrage')->sole()->quantity)->toBe(40.0)
        ->and(Stock::query()->where('name', 'Aliment démarrage')->sole()->category->type->value)->toBe('feed');

    $employees = writeWorkbook(['Prénom', 'Nom', 'Genre', 'Date d\'embauche', 'Mode de paiement'], [['Ama', 'Koné', 'femme', '01/02/2024', 'mobile_money']]);
    $result = $this->service->import('employees', $employees, $this->organisation);
    expect($result['errors'])->toBe([])->and($result['imported'])->toBe(1)
        ->and(Employee::query()->where('last_name', 'Koné')->sole()->hire_date->toDateString())->toBe('2024-02-01');
});

it('importe factures groupées par numéro et dépenses, encaissées et payées à leur date', function () {
    Customer::create(['organisation_id' => $this->organisation->id, 'name' => 'Maquis Chez Tantie', 'type' => 'restaurant']);
    app(AccountService::class)->createAccount(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'name' => 'Caisse', 'type' => AccountType::Cash, 'initial_balance' => 1000000, 'initial_balance_date' => '2025-01-01']);

    $sales = writeWorkbook(['Numéro', 'Date', 'Client', 'Désignation', 'Quantité', 'Prix unitaire', 'Payée'], [
        ['F2025-001', '15/03/2025', 'Maquis Chez Tantie', 'Plateau de 30 œufs', 50, 3000, 'oui'],
        ['F2025-001', '15/03/2025', 'Maquis Chez Tantie', 'Poulet vif', 20, 3500, 'oui'],
        ['', '20/03/2025', 'Maquis Chez Tantie', 'Plateau de 30 œufs', 2, 3000, 'non'],
    ]);
    $result = $this->service->import('sales', $sales, $this->organisation, $this->owner->id);

    expect($result['errors'])->toBe([])
        ->and($result['imported'])->toBe(2);

    $invoice = Sale::query()->where('invoice_number', 'F2025-001')->sole();

    expect($invoice->items()->count())->toBe(2)
        ->and((float) $invoice->total)->toBe(220000.0)
        ->and($invoice->fresh()->status)->toBe(SaleStatus::Paid)
        ->and($invoice->payments()->sole()->payment_date->toDateString())->toBe('2025-03-15')
        ->and(Sale::query()->where('organisation_id', $this->organisation->id)->where('status', SaleStatus::Pending->value)->count())->toBe(1);

    $expenses = writeWorkbook(['Date', 'Catégorie', 'Description', 'Montant', 'Fournisseur', 'Payée'], [
        ['03/03/2025', 'Aliment', '40 sacs aliment démarrage', 740000, 'FACI', 'oui'],
        ['10/03/2025', 'Électricité', 'Facture CIE', 85000, '', 'non'],
    ]);
    $result = $this->service->import('expenses', $expenses, $this->organisation, $this->owner->id);

    expect($result['errors'])->toBe([])
        ->and($result['imported'])->toBe(2)
        ->and(Supplier::query()->where('name', 'FACI')->count())->toBe(1);

    $paid = Expense::query()->where('description', '40 sacs aliment démarrage')->sole();

    expect($paid->status)->toBe(ExpenseStatus::Paid)
        ->and($paid->category->value)->toBe('feed')
        ->and($paid->paid_at->toDateString())->toBe('2025-03-03')
        ->and($paid->account->transactions()->where('reference_number', $paid->expense_number)->sole()->transaction_date->toDateString())->toBe('2025-03-03');
});

it('refuse tout le fichier à la première erreur et rapporte chaque ligne fautive', function () {
    $customers = writeWorkbook(['Nom', 'Type', 'Ferme'], [
        ['Bon client', 'particulier', ''],
        ['', 'particulier', ''],
        ['Mauvais type', 'grossiste', ''],
        ['Mauvaise ferme', '', 'Ferme Inconnue'],
    ]);
    $result = $this->service->import('customers', $customers, $this->organisation);

    expect($result['imported'])->toBe(0)
        ->and($result['errors'])->toHaveCount(3)
        ->and($result['errors'][0])->toContain('Ligne 3')->toContain('Nom')
        ->and($result['errors'][1])->toContain('Ligne 4')->toContain('grossiste')
        ->and($result['errors'][2])->toContain('Ligne 5')->toContain('Ferme Inconnue')
        ->and(Customer::query()->where('organisation_id', $this->organisation->id)->count())->toBe(0);

    $wrongHeaders = writeWorkbook(['Client', 'Tel'], [['x', 'y']]);
    expect($this->service->import('customers', $wrongHeaders, $this->organisation)['errors'][0])->toContain('Colonnes obligatoires absentes');
});

it('la page d\'import est réservée aux membres qui administrent les paramètres et propose le modèle', function () {
    actingAs($this->owner);
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($this->organisation);

    expect(DataImport::canAccess())->toBeTrue();

    Livewire::test(DataImport::class)
        ->assertOk()
        ->assertSee('Télécharger le modèle')
        ->assertSee('Factures de vente')
        ->assertFormSet(['type' => 'customers']);

    $employee = User::factory()->create();
    $this->organisation->addMember($employee, 'employee');
    actingAs($employee);

    expect(DataImport::canAccess())->toBeFalse();
});
