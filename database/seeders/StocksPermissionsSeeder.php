<?php

namespace Database\Seeders;

use App\Enums\PlanFeature;
use App\Models\Organisation;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Propage les permissions et features du module Stocks :
 * - crée les permissions (stock_categories, stocks, stock_lots, stock_movements, purchase_orders + actions custom)
 * - resynchronise les rôles système des organisations existantes
 * - ajoute StockBatches, PurchaseOrders et StockAlerts au plan Pro existant
 *
 * Idempotent — peut être relancé sans effet de bord.
 */
class StocksPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(OrganisationPermissionSeeder::class);

        Organisation::query()->withTrashed()->each(
            fn (Organisation $organisation) => $organisation->createSystemRoles(),
        );

        $proPlan = Plan::where('slug', 'pro')->first();

        if ($proPlan) {
            $features = collect($proPlan->features ?? [])
                ->push(PlanFeature::StockBatches->value)
                ->push(PlanFeature::PurchaseOrders->value)
                ->push(PlanFeature::Inventories->value)
                ->push(PlanFeature::StockAlerts->value)
                ->unique()
                ->values()
                ->all();

            $proPlan->update(['features' => $features]);
        }
    }
}
