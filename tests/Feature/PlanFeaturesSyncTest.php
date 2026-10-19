<?php

use App\Enums\PlanFeature;
use App\Models\Plan;
use Database\Seeders\PlanSeeder;

/**
 * Fonctionnalités des plans : le plan Entreprise couvre toujours tout, le plan Pro inclut les
 * livraisons, y compris sur une base où les plans existaient déjà (seeder en firstOrCreate).
 */
it('complète un plan Entreprise existant au passage du seeder et donne les livraisons au plan Pro neuf', function () {
    $enterprise = Plan::factory()->create(['slug' => 'enterprise', 'features' => [PlanFeature::Accounting->value]]);

    $this->seed(PlanSeeder::class);

    expect($enterprise->fresh()->features)->toEqualCanonicalizing(collect(PlanFeature::cases())->map->value->all())
        ->and(Plan::query()->where('slug', 'pro')->sole()->hasFeature(PlanFeature::Deliveries))->toBeTrue()
        ->and(Plan::query()->where('slug', 'starter')->sole()->hasFeature(PlanFeature::Deliveries))->toBeFalse();
});

it('donne au plan Pro les tableaux de bord commercial, financier, RH et paie, mais pas celui de l\'IoT', function () {
    $this->seed(PlanSeeder::class);
    $seeded = Plan::query()->where('slug', 'pro')->sole();

    $existing = Plan::factory()->create(['slug' => 'pro-existant', 'features' => [PlanFeature::Invoices->value]]);
    $seeded->update(['slug' => 'pro-seede']);
    $existing->update(['slug' => 'pro']);

    (require database_path('migrations/2026_10_19_100000_add_dashboards_to_pro_plan.php'))->up();

    foreach ([$seeded->fresh(), $existing->fresh()] as $pro) {
        expect($pro->hasFeature(PlanFeature::SalesDashboard))->toBeTrue()
            ->and($pro->hasFeature(PlanFeature::FinanceDashboard))->toBeTrue()
            ->and($pro->hasFeature(PlanFeature::HrDashboard))->toBeTrue()
            ->and($pro->hasFeature(PlanFeature::PayrollDashboard))->toBeTrue()
            ->and($pro->hasFeature(PlanFeature::IotDashboard))->toBeFalse();
    }

    expect($existing->fresh()->hasFeature(PlanFeature::Invoices))->toBeTrue();
});

it('la migration ajoute les dépenses au plan Entreprise et les livraisons au plan Pro existants, sans rien retirer', function () {
    $enterprise = Plan::factory()->create(['slug' => 'enterprise', 'features' => [PlanFeature::Accounting->value]]);
    $pro = Plan::factory()->create(['slug' => 'pro', 'features' => [PlanFeature::Invoices->value, 'option_maison']]);

    (require database_path('migrations/2026_10_19_090000_sync_plan_features.php'))->up();

    expect($enterprise->fresh()->hasFeature(PlanFeature::Expenses))->toBeTrue()
        ->and($enterprise->fresh()->features)->toHaveCount(count(PlanFeature::cases()))
        ->and($pro->fresh()->features)->toEqualCanonicalizing([PlanFeature::Invoices->value, 'option_maison', PlanFeature::Deliveries->value]);
});
