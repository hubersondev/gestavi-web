<?php

use App\Filament\Farm\Pages\GroupDashboard;
use App\Filament\Farm\Pages\Reports\AccountingReportPage;
use App\Filament\Farm\Pages\Reports\CommercialReportPage;
use App\Filament\Farm\Pages\Reports\FinancialReportPage;
use App\Filament\Farm\Pages\Reports\FlockCostReportPage;
use App\Filament\Farm\Pages\Reports\ProductionReportPage;
use App\Filament\Farm\Pages\Reports\StockReportPage;
use App\Models\Organisation;
use App\Models\User;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * Les filtres de période des rapports tiennent sur une grille : deux champs par ligne sur tablette,
 * quatre sur grand écran, au lieu d'un champ de date pleine largeur par ligne.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);
    $organisation = Organisation::factory()->create();
    $owner = User::factory()->create();
    $organisation->addMember($owner, 'owner');

    actingAs($owner);
    Filament::setTenant($organisation);
});

it('dispose les filtres de chaque rapport sur une grille responsive', function (string $page) {
    $schema = Livewire::test($page)->assertOk()->instance()->getSchema('filtersForm');

    expect($schema->getColumns())->toBe(['sm' => 2, 'lg' => 4]);
})->with([
    'tableau de bord groupe' => GroupDashboard::class,
    'production' => ProductionReportPage::class,
    'financier' => FinancialReportPage::class,
    'commercial' => CommercialReportPage::class,
    'stocks' => StockReportPage::class,
    'coût de revient' => FlockCostReportPage::class,
    'comptabilité' => AccountingReportPage::class,
]);
