<?php

namespace Database\Seeders;

use App\Enums\PlanFeature;
use App\Models\Organisation;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Propage les permissions et features du module Commercial :
 * - crée les permissions (customers, sales, quotes, payments + actions custom)
 * - resynchronise les rôles système des organisations existantes
 * - ajoute Quotes au plan Pro (les autres features sont déjà présentes)
 *
 * Idempotent — peut être relancé sans effet de bord.
 */
class CommercialPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Crée/vérifie les permissions via le seeder global idempotent
        $this->call(OrganisationPermissionSeeder::class);

        // 2) Resynchronise les rôles système des organisations existantes
        Organisation::query()->withTrashed()->each(
            fn (Organisation $organisation) => $organisation->createSystemRoles(),
        );

        // 3) Ajoute les features Commercial manquantes au plan Pro existant
        $proPlan = Plan::where('slug', 'pro')->first();

        if ($proPlan) {
            $features = collect($proPlan->features ?? [])
                ->push(PlanFeature::Customers->value)
                ->push(PlanFeature::Invoices->value)
                ->push(PlanFeature::Quotes->value)
                ->push(PlanFeature::CreditNotes->value)
                ->push(PlanFeature::Payments->value)
                ->unique()
                ->values()
                ->all();

            $proPlan->update(['features' => $features]);
        }
    }
}
