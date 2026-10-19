<?php

namespace Database\Seeders;

use App\Enums\PlanFeature;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $starterFeatures = [
            PlanFeature::Buildings->value,
            PlanFeature::Flocks->value,
            PlanFeature::DailyRecords->value,
            PlanFeature::StockManagement->value,
            PlanFeature::MainDashboard->value,
            PlanFeature::TeamManagement->value,
        ];

        $proFeatures = array_merge($starterFeatures, [
            PlanFeature::MultiFarms->value,
            PlanFeature::Suppliers->value,
            PlanFeature::Equipment->value,
            PlanFeature::EquipmentMaintenance->value,
            PlanFeature::ProductionPhases->value,
            PlanFeature::HealthPrograms->value,
            PlanFeature::Vaccinations->value,
            PlanFeature::Customers->value,
            PlanFeature::Invoices->value,
            PlanFeature::Quotes->value,
            PlanFeature::CreditNotes->value,
            PlanFeature::Payments->value,
            PlanFeature::Deliveries->value,
            // Tableaux de bord des modules inclus (l'IoT n'est pas dans l'offre Pro)
            PlanFeature::SalesDashboard->value,
            PlanFeature::FinanceDashboard->value,
            PlanFeature::HrDashboard->value,
            PlanFeature::PayrollDashboard->value,
            PlanFeature::Employees->value,
            PlanFeature::Contracts->value,
            PlanFeature::LeaveManagement->value,
            PlanFeature::Attendance->value,
            PlanFeature::Payroll->value,
            PlanFeature::CnpsCalculation->value,
            PlanFeature::Accounting->value,
            PlanFeature::Transfers->value,
            PlanFeature::BankReconciliation->value,
            PlanFeature::Expenses->value,
            PlanFeature::StockBatches->value,
            PlanFeature::PurchaseOrders->value,
            PlanFeature::Inventories->value,
            PlanFeature::StockAlerts->value,
            PlanFeature::Reports->value,
            PlanFeature::Notifications->value,
            PlanFeature::ExcelExports->value,
            // Prévisions à 7 jours par bande (page Production → Prévisions)
            PlanFeature::AiPredictions->value,
            PlanFeature::WeatherIntegration->value,
            PlanFeature::MultiFarmComparison->value,
        ]);

        $enterpriseFeatures = collect(PlanFeature::cases())
            ->map(fn (PlanFeature $f) => $f->value)
            ->all();

        Plan::firstOrCreate(
            ['slug' => 'starter'],
            [
                'name' => 'Starter',
                'description' => 'Plan gratuit pour démarrer avec les fonctionnalités essentielles.',
                'monthly_price' => 0,
                'yearly_price' => 0,
                'duration_months' => 1,
                'max_farms' => 1,
                'max_users' => 3,
                'trial_days' => 0,
                'features' => $starterFeatures,
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        Plan::firstOrCreate(
            ['slug' => 'pro'],
            [
                'name' => 'Pro',
                'description' => 'Pour les exploitations en croissance avec des besoins avancés.',
                'monthly_price' => 25000,
                'yearly_price' => 250000,
                'duration_months' => 1,
                'max_farms' => 5,
                'max_users' => 15,
                'trial_days' => 14,
                'features' => $proFeatures,
                'sort_order' => 2,
                'is_active' => true,
            ],
        );

        $enterprise = Plan::firstOrCreate(
            ['slug' => 'enterprise'],
            [
                'name' => 'Entreprise',
                'description' => 'Accès illimité à toutes les fonctionnalités pour les grandes structures.',
                'monthly_price' => 75000,
                'yearly_price' => 750000,
                'duration_months' => 1,
                'max_farms' => null,
                'max_users' => null,
                'trial_days' => 30,
                'features' => $enterpriseFeatures,
                'sort_order' => 3,
                'is_active' => true,
            ],
        );

        // Le plan Entreprise donne accès à tout : il reçoit aussi les fonctionnalités ajoutées depuis sa création
        if (array_diff($enterpriseFeatures, $enterprise->features ?? []) !== []) {
            $enterprise->update(['features' => $enterpriseFeatures]);
        }
    }
}
